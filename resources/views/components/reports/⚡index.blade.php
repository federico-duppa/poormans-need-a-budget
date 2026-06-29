<?php

use App\Models\Budget;
use App\Services\ReportService;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public string $month;

    public function mount(): void
    {
        $this->month = CarbonImmutable::now()->startOfMonth()->toDateString();
    }

    private function budget(): Budget
    {
        return auth()->user()->currentBudget();
    }

    private function reports(): ReportService
    {
        return app(ReportService::class);
    }

    public function changeMonth(int $delta): void
    {
        $this->month = CarbonImmutable::parse($this->month)->addMonths($delta)->startOfMonth()->toDateString();
        unset($this->spending, $this->incomeVsExpense);
    }

    #[Computed]
    public function spending(): Collection
    {
        return $this->reports()->spendingByCategory($this->budget(), $this->month);
    }

    #[Computed]
    public function incomeVsExpense(): Collection
    {
        return $this->reports()->incomeVsExpense($this->budget(), 6, $this->month);
    }

    #[Computed]
    public function ageOfMoney(): ?int
    {
        return $this->reports()->ageOfMoney($this->budget(), CarbonImmutable::parse($this->month)->endOfMonth()->toDateString());
    }

    public function fmt(int $cents): string
    {
        return Money::format($cents, $this->budget()->base_currency);
    }

    public function monthLabel(): string
    {
        $meses = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
            'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
        $date = CarbonImmutable::parse($this->month);

        return ucfirst($meses[(int) $date->format('n')]).' '.$date->format('Y');
    }
}; ?>

<div class="space-y-6">
    {{-- Navegación de mes --}}
    <div class="flex items-center justify-between">
        <button wire:click="changeMonth(-1)" class="rounded-full bg-slate-100 p-2 text-slate-600 hover:bg-slate-200" aria-label="Mes anterior">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/></svg>
        </button>
        <span class="text-sm font-semibold text-slate-700">{{ $this->monthLabel() }}</span>
        <button wire:click="changeMonth(1)" class="rounded-full bg-slate-100 p-2 text-slate-600 hover:bg-slate-200" aria-label="Mes siguiente">
            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
        </button>
    </div>

    {{-- Age of Money --}}
    <div class="rounded-2xl bg-slate-800 p-5 text-white">
        <p class="text-sm text-slate-300">Edad del dinero (Age of Money)</p>
        @if ($this->ageOfMoney !== null)
            <p class="mt-1 text-3xl font-bold">{{ $this->ageOfMoney }} <span class="text-lg font-normal text-slate-300">días</span></p>
            <p class="mt-2 text-xs text-slate-400">
                Cuánto tiempo "viven" tus pesos antes de gastarse. 30+ días = vivís del dinero del mes pasado. 🎉
            </p>
        @else
            <p class="mt-1 text-lg text-slate-300">Sin datos suficientes todavía.</p>
        @endif
    </div>

    {{-- Gasto por categoría --}}
    <div>
        <h2 class="mb-3 text-base font-semibold text-slate-700">Gasto por categoría</h2>
        @php $maxSpending = $this->spending->max('total') ?: 1; @endphp
        <div class="space-y-3 rounded-2xl border border-slate-200 bg-white p-4">
            @forelse ($this->spending as $row)
                <div>
                    <div class="mb-1 flex items-center justify-between text-sm">
                        <span class="font-medium text-slate-700">{{ $row['name'] }}</span>
                        <span class="text-slate-500">{{ $this->fmt(-$row['total']) }}</span>
                    </div>
                    <div class="h-2 overflow-hidden rounded-full bg-slate-100">
                        <div class="h-full rounded-full bg-emerald-500" style="width: {{ max(3, round($row['total'] / $maxSpending * 100)) }}%"></div>
                    </div>
                </div>
            @empty
                <p class="text-center text-sm text-slate-400">No hubo gastos este mes.</p>
            @endforelse
        </div>
    </div>

    {{-- Ingreso vs egreso --}}
    <div>
        <h2 class="mb-3 text-base font-semibold text-slate-700">Ingreso vs egreso (6 meses)</h2>
        @php $maxFlow = max($this->incomeVsExpense->max('income'), $this->incomeVsExpense->max('expense')) ?: 1; @endphp
        <div class="space-y-3 rounded-2xl border border-slate-200 bg-white p-4">
            @foreach ($this->incomeVsExpense as $row)
                <div>
                    <p class="mb-1 text-xs font-medium text-slate-500">{{ $row['label'] }}</p>
                    <div class="flex items-center gap-2">
                        <div class="h-3 flex-1 overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full rounded-full bg-emerald-500" style="width: {{ round($row['income'] / $maxFlow * 100) }}%"></div>
                        </div>
                        <span class="w-24 text-right text-[11px] text-emerald-700">{{ $this->fmt($row['income']) }}</span>
                    </div>
                    <div class="mt-1 flex items-center gap-2">
                        <div class="h-3 flex-1 overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full rounded-full bg-red-400" style="width: {{ round($row['expense'] / $maxFlow * 100) }}%"></div>
                        </div>
                        <span class="w-24 text-right text-[11px] text-red-600">{{ $this->fmt(-$row['expense']) }}</span>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
