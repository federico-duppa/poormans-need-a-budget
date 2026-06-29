<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\Category;
use App\Models\CategoryMonth;
use App\Models\MonthlyBudget;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

/**
 * Motor de presupuesto base-cero al estilo YNAB.
 *
 * Invariante fundamental:
 *   saldo total de cuentas on-budget = Ready to Assign + Σ disponible de categorías
 *
 * Todos los montos se manejan en centavos de la moneda base del presupuesto
 * (campo amount_base de las transacciones).
 *
 * Arrastre (Rule 3 "Roll with the punches"): el "disponible" de una categoría
 * es acumulativo: Σ (asignado + actividad) de todos los meses hasta el mes dado.
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
     */
    public function activity(Budget $budget, Category $category, string|CarbonImmutable|Carbon $month): int
    {
        $monthDate = CarbonImmutable::parse($this->normalizeMonth($month));

        return (int) Transaction::query()
            ->where('category_id', $category->id)
            ->whereIn('account_id', $this->onBudgetAccountIds($budget))
            ->whereBetween('date', [$monthDate->startOfMonth(), $monthDate->endOfMonth()])
            ->sum('amount_base');
    }

    /**
     * Disponible acumulado de una categoría hasta (incluido) el mes dado.
     * available = Σ asignado(≤mes) + Σ actividad(≤mes)
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

        $activity = (int) Transaction::query()
            ->where('category_id', $category->id)
            ->whereIn('account_id', $this->onBudgetAccountIds($budget))
            ->whereDate('date', '<=', $monthEnd)
            ->sum('amount_base');

        return $assigned + $activity;
    }

    /**
     * Dinero listo para asignar (Ready to Assign), global del presupuesto.
     *
     * RTA = saldo cuentas on-budget − Σ disponible de todas las categorías
     *     = (ingresos sin categorizar) − (total asignado)
     */
    public function readyToAssign(Budget $budget): int
    {
        $onBudgetBalance = (int) Transaction::query()
            ->whereIn('account_id', $this->onBudgetAccountIds($budget))
            ->sum('amount_base');

        $totalAssigned = (int) CategoryMonth::query()
            ->whereHas('monthlyBudget', fn ($q) => $q->where('budget_id', $budget->id))
            ->sum('assigned');

        $totalCategorizedActivity = (int) Transaction::query()
            ->whereIn('account_id', $this->onBudgetAccountIds($budget))
            ->whereNotNull('category_id')
            ->sum('amount_base');

        $totalAvailable = $totalAssigned + $totalCategorizedActivity;

        return $onBudgetBalance - $totalAvailable;
    }

    /**
     * IDs de las cuentas on-budget del presupuesto.
     *
     * @return array<int, int>
     */
    protected function onBudgetAccountIds(Budget $budget): array
    {
        return $budget->accounts()->where('on_budget', true)->pluck('id')->all();
    }
}
