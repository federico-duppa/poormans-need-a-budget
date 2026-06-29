<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\CategoryMonth;
use App\Models\MonthlyBudget;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

/**
 * Motor de presupuesto base-cero (método de sobres).
 *
 * Invariante fundamental:
 *   saldo total de cuentas on-budget = dinero por asignar + Σ disponible de categorías
 *
 * Todos los montos se manejan en centavos de la moneda base del presupuesto
 * (campo amount_base de las transacciones).
 *
 * Arrastre (flexibilidad mes a mes): el "disponible" de una categoría es
 * acumulativo: Σ (asignado + actividad) de todos los meses hasta el mes dado.
 */
class BudgetService
{
    /**
     * Normaliza un mes a la fecha del primer día (YYYY-MM-01).
     */
    public function normalizeMonth(string|CarbonImmutable|Carbon $month): string
    {
        if (is_string($month)) {
            // Acepta "YYYY-MM" o "YYYY-MM-DD".
            $month = CarbonImmutable::parse(strlen($month) === 7 ? $month.'-01' : $month);
        }

        return CarbonImmutable::parse($month)->startOfMonth()->toDateString();
    }

    /**
     * Devuelve (creando si falta) el mes presupuestario.
     */
    public function monthlyBudget(Budget $budget, string|CarbonImmutable|Carbon $month): MonthlyBudget
    {
        $monthDate = $this->normalizeMonth($month);

        // whereDate evita falsos negativos por el componente horario del campo date.
        $existing = MonthlyBudget::query()
            ->where('budget_id', $budget->id)
            ->whereDate('month', $monthDate)
            ->first();

        return $existing ?? MonthlyBudget::create([
            'budget_id' => $budget->id,
            'month' => $monthDate,
        ]);
    }

    /**
     * Asigna (fija) un monto a una categoría en un mes dado.
     */
    public function assign(Budget $budget, Category $category, string|CarbonImmutable|Carbon $month, int $cents): CategoryMonth
    {
        $monthlyBudget = $this->monthlyBudget($budget, $month);

        return CategoryMonth::updateOrCreate(
            [
                'monthly_budget_id' => $monthlyBudget->id,
                'category_id' => $category->id,
            ],
            ['assigned' => $cents],
        );
    }

    /**
     * Mueve dinero de una categoría a otra dentro del mismo mes.
     */
    public function move(Budget $budget, Category $from, Category $to, string|CarbonImmutable|Carbon $month, int $cents): void
    {
        $this->assign($budget, $from, $month, $this->assigned($budget, $from, $month) - $cents);
        $this->assign($budget, $to, $month, $this->assigned($budget, $to, $month) + $cents);
    }

    /**
     * Monto asignado a una categoría en un mes (0 si no hay registro).
     */
    public function assigned(Budget $budget, Category $category, string|CarbonImmutable|Carbon $month): int
    {
        $monthDate = $this->normalizeMonth($month);

        return (int) CategoryMonth::query()
            ->where('category_id', $category->id)
            ->whereHas('monthlyBudget', fn ($q) => $q
                ->where('budget_id', $budget->id)
                ->whereDate('month', $monthDate))
            ->sum('assigned');
    }

    /**
     * Actividad (suma de movimientos) de una categoría en un mes, en moneda base.
     * Negativo = gasto.
     *
     * Para categorías de pago de tarjeta, la "actividad" del mes combina los
     * fondos que ingresan desde las compras a crédito (positivo) y los pagos
     * realizados a la tarjeta (negativo).
     */
    public function activity(Budget $budget, Category $category, string|CarbonImmutable|Carbon $month): int
    {
        $start = CarbonImmutable::parse($this->normalizeMonth($month))->startOfMonth();
        $end = CarbonImmutable::parse($this->normalizeMonth($month))->endOfMonth();

        if ($this->isPaymentCategory($category)) {
            return $this->creditFunded($category, end: $end, start: $start)
                + $this->paymentActivity($category, end: $end, start: $start);
        }

        return (int) Transaction::query()
            ->where('category_id', $category->id)
            ->whereIn('account_id', $this->onBudgetAccountIds($budget))
            ->whereBetween('date', [$start, $end])
            ->sum('amount_base');
    }

    /**
     * Disponible acumulado de una categoría hasta (incluido) el mes dado.
     *
     * Categoría normal: available = Σ asignado(≤mes) + Σ actividad(≤mes)
     * Categoría de pago: available = Σ asignado(≤mes) + fondos de crédito(≤mes) + pagos(≤mes)
     */
    public function available(Budget $budget, Category $category, string|CarbonImmutable|Carbon $month): int
    {
        $monthEnd = CarbonImmutable::parse($this->normalizeMonth($month))->endOfMonth();

        $assigned = (int) CategoryMonth::query()
            ->where('category_id', $category->id)
            ->whereHas('monthlyBudget', fn ($q) => $q
                ->where('budget_id', $budget->id)
                ->whereDate('month', '<=', $monthEnd->toDateString()))
            ->sum('assigned');

        if ($this->isPaymentCategory($category)) {
            return $assigned
                + $this->creditFunded($category, end: $monthEnd)
                + $this->paymentActivity($category, end: $monthEnd);
        }

        $activity = (int) Transaction::query()
            ->where('category_id', $category->id)
            ->whereIn('account_id', $this->onBudgetAccountIds($budget))
            ->whereDate('date', '<=', $monthEnd)
            ->sum('amount_base');

        return $assigned + $activity;
    }

    /**
     * Dinero listo para asignar, global del presupuesto.
     *
     * Las tarjetas de crédito se excluyen del lado "efectivo": gastar a crédito
     * no reduce el RTA (reduce el disponible de la categoría del gasto), y el
     * dinero para pagar la tarjeta queda reservado en su categoría de pago.
     *
     *   RTA = saldo cuentas on-budget NO-tarjeta − Σ disponible de todas las categorías
     */
    public function readyToAssign(Budget $budget): int
    {
        $cashBalance = (int) Transaction::query()
            ->whereIn('account_id', $this->cashAccountIds($budget))
            ->sum('amount_base');

        $totalAssigned = (int) CategoryMonth::query()
            ->whereHas('monthlyBudget', fn ($q) => $q->where('budget_id', $budget->id))
            ->sum('assigned');

        // Actividad de categorías normales (incluye compras hechas con tarjeta).
        $normalActivity = (int) Transaction::query()
            ->whereIn('account_id', $this->onBudgetAccountIds($budget))
            ->whereNotNull('category_id')
            ->whereNotIn('category_id', $this->paymentCategoryIds($budget))
            ->sum('amount_base');

        // Fondos que las compras a crédito reservan en las categorías de pago.
        $paymentFunded = -1 * (int) Transaction::query()
            ->whereIn('account_id', $this->creditCardAccountIds($budget))
            ->whereNotNull('category_id')
            ->whereNotIn('category_id', $this->paymentCategoryIds($budget))
            ->sum('amount_base');

        // Pagos hechos a las tarjetas (movimientos categorizados a la categoría de pago).
        $paymentActivity = (int) Transaction::query()
            ->whereIn('account_id', $this->onBudgetAccountIds($budget))
            ->whereIn('category_id', $this->paymentCategoryIds($budget))
            ->sum('amount_base');

        $totalAvailable = $totalAssigned + $normalActivity + $paymentFunded + $paymentActivity;

        return $cashBalance - $totalAvailable;
    }

    /**
     * Registra un pago de tarjeta como transferencia de dos patas:
     *  - salida de la cuenta de efectivo, categorizada a la categoría de pago
     *  - entrada a la tarjeta (reduce la deuda), sin categoría
     *
     * MVP: ambas cuentas en la misma moneda.
     */
    public function payCreditCard(Account $from, Account $card, int $cents, string $date, ?int $userId = null): void
    {
        \Illuminate\Support\Facades\DB::transaction(function () use ($from, $card, $cents, $date, $userId) {
            $paymentCategory = $card->paymentCategory()->firstOrFail();

            $outflow = Transaction::create([
                'account_id' => $from->id,
                'date' => $date,
                'amount' => -$cents,
                'currency' => $from->currency,
                'category_id' => $paymentCategory->id,
                'user_id' => $userId,
                'cleared' => true,
            ]);

            $inflow = Transaction::create([
                'account_id' => $card->id,
                'date' => $date,
                'amount' => $cents,
                'currency' => $card->currency,
                'user_id' => $userId,
                'cleared' => true,
                'transfer_pair_id' => $outflow->id,
            ]);

            $outflow->update(['transfer_pair_id' => $inflow->id]);
        });
    }

    // --- Helpers de tarjetas ------------------------------------------------

    public function isPaymentCategory(Category $category): bool
    {
        return $category->linked_account_id !== null;
    }

    /**
     * Fondos que las compras a crédito reservan en la categoría de pago (positivo).
     */
    protected function creditFunded(Category $paymentCategory, CarbonImmutable $end, ?CarbonImmutable $start = null): int
    {
        $budget = $paymentCategory->group->budget;

        return -1 * (int) Transaction::query()
            ->where('account_id', $paymentCategory->linked_account_id)
            ->whereNotNull('category_id')
            ->whereNotIn('category_id', $this->paymentCategoryIds($budget))
            ->when($start, fn ($q) => $q->whereDate('date', '>=', $start))
            ->whereDate('date', '<=', $end)
            ->sum('amount_base');
    }

    /**
     * Pagos realizados a la tarjeta (movimientos categorizados a la categoría de pago, negativo).
     */
    protected function paymentActivity(Category $paymentCategory, CarbonImmutable $end, ?CarbonImmutable $start = null): int
    {
        return (int) Transaction::query()
            ->where('category_id', $paymentCategory->id)
            ->when($start, fn ($q) => $q->whereDate('date', '>=', $start))
            ->whereDate('date', '<=', $end)
            ->sum('amount_base');
    }

    /**
     * IDs de cuentas on-budget (incluye tarjetas de crédito).
     *
     * @return array<int, int>
     */
    protected function onBudgetAccountIds(Budget $budget): array
    {
        return $budget->accounts()->where('on_budget', true)->pluck('id')->all();
    }

    /**
     * IDs de cuentas on-budget que NO son tarjeta de crédito (efectivo/banco).
     *
     * @return array<int, int>
     */
    protected function cashAccountIds(Budget $budget): array
    {
        return $budget->accounts()->where('on_budget', true)
            ->where('type', '!=', 'credit_card')->pluck('id')->all();
    }

    /** @return array<int, int> */
    protected function creditCardAccountIds(Budget $budget): array
    {
        return $budget->accounts()->where('type', 'credit_card')->pluck('id')->all();
    }

    /**
     * IDs de las categorías de pago de tarjeta del presupuesto.
     *
     * @return array<int, int>
     */
    protected function paymentCategoryIds(Budget $budget): array
    {
        return Category::query()
            ->whereNotNull('linked_account_id')
            ->whereHas('group', fn ($q) => $q->where('budget_id', $budget->id))
            ->pluck('id')->all();
    }
}
