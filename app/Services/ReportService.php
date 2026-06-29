<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class ReportService
{
    /**
     * Gasto por categoría en un mes (montos positivos), de mayor a menor.
     *
     * @return Collection<int, array{name: string, group: string, total: int}>
     */
    public function spendingByCategory(Budget $budget, string $month): Collection
    {
        $date = CarbonImmutable::parse(strlen($month) === 7 ? $month.'-01' : $month);
        $accountIds = $budget->accounts()->pluck('id');

        return Transaction::query()
            ->select('category_id')
            ->selectRaw('SUM(amount_base) as total')
            ->whereIn('account_id', $accountIds)
            ->whereNotNull('category_id')
            ->whereBetween('date', [$date->startOfMonth(), $date->endOfMonth()])
            ->where('amount_base', '<', 0)
            ->groupBy('category_id')
            ->with('category.group')
            ->get()
            ->map(fn ($row) => [
                'name' => $row->category?->name ?? 'Sin categoría',
                'group' => $row->category?->group?->name ?? '',
                'total' => abs((int) $row->total),
            ])
            ->sortByDesc('total')
            ->values();
    }

    /**
     * Ingreso vs egreso por mes, para los últimos N meses (incluido el actual).
     *
     * @return Collection<int, array{month: string, label: string, income: int, expense: int}>
     */
    public function incomeVsExpense(Budget $budget, int $monthsBack = 6, ?string $until = null): Collection
    {
        $accountIds = $budget->accounts()->pluck('id');
        $end = CarbonImmutable::parse($until ? (strlen($until) === 7 ? $until.'-01' : $until) : 'now')->startOfMonth();

        $result = collect();
        for ($i = $monthsBack - 1; $i >= 0; $i--) {
            $m = $end->subMonths($i);

            $income = (int) Transaction::query()
                ->whereIn('account_id', $accountIds)
                ->whereBetween('date', [$m->startOfMonth(), $m->endOfMonth()])
                ->where('amount_base', '>', 0)
                ->whereNull('transfer_pair_id')
                ->sum('amount_base');

            $expense = abs((int) Transaction::query()
                ->whereIn('account_id', $accountIds)
                ->whereBetween('date', [$m->startOfMonth(), $m->endOfMonth()])
                ->where('amount_base', '<', 0)
                ->whereNull('transfer_pair_id')
                ->sum('amount_base'));

            $result->push([
                'month' => $m->toDateString(),
                'label' => $m->format('m/Y'),
                'income' => $income,
                'expense' => $expense,
            ]);
        }

        return $result;
    }

    /**
     * Age of Money (Rule 4): edad promedio del dinero gastado, en días.
     *
     * Usa FIFO: empareja cada egreso con los ingresos más antiguos disponibles
     * y promedia (ponderado por monto) la antigüedad de los últimos 10 egresos.
     * Solo considera cuentas de efectivo/banco (no tarjetas).
     */
    public function ageOfMoney(Budget $budget, ?string $until = null): ?int
    {
        $cashAccountIds = $budget->accounts()
            ->where('on_budget', true)->where('type', '!=', 'credit_card')->pluck('id');

        $end = CarbonImmutable::parse($until ?: 'now')->endOfDay();

        $txns = Transaction::query()
            ->whereIn('account_id', $cashAccountIds)
            ->whereDate('date', '<=', $end)
            ->whereNull('transfer_pair_id')
            ->orderBy('date')->orderBy('id')
            ->get(['date', 'amount_base']);

        // Cola FIFO de ingresos disponibles: [['date'=>CarbonImmutable, 'remaining'=>int]]
        $inflows = [];
        $outflowAges = []; // [['age'=>int, 'amount'=>int]]

        foreach ($txns as $tx) {
            $amount = (int) $tx->amount_base;
            $txDate = CarbonImmutable::parse($tx->date);

            if ($amount > 0) {
                $inflows[] = ['date' => $txDate, 'remaining' => $amount];

                continue;
            }

            $toConsume = -$amount;
            $weightedAge = 0;
            $consumed = 0;

            foreach ($inflows as &$inflow) {
                if ($toConsume <= 0) {
                    break;
                }
                if ($inflow['remaining'] <= 0) {
                    continue;
                }
                $take = min($inflow['remaining'], $toConsume);
                $age = $inflow['date']->diffInDays($txDate);
                $weightedAge += $take * $age;
                $consumed += $take;
                $inflow['remaining'] -= $take;
                $toConsume -= $take;
            }
            unset($inflow);

            if ($consumed > 0) {
                $outflowAges[] = ['age' => $weightedAge / $consumed, 'amount' => $consumed];
            }
        }

        if (empty($outflowAges)) {
            return null;
        }

        // Promedio ponderado de los últimos 10 egresos.
        $recent = array_slice($outflowAges, -10);
        $totalAmount = array_sum(array_column($recent, 'amount'));
        if ($totalAmount === 0) {
            return null;
        }

        $weighted = array_sum(array_map(fn ($o) => $o['age'] * $o['amount'], $recent));

        return (int) round($weighted / $totalAmount);
    }
}
