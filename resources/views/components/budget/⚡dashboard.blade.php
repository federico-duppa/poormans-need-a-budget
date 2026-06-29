<?php

use App\Models\Budget;
use App\Services\BudgetService;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    /** Mes activo en formato YYYY-MM-01. */
    public string $month;

    /** Inputs de asignación por categoría: [categoryId => "30000.00"]. */
    public array $assignedInputs = [];

    public function mount(): void
    {
        $this->month = CarbonImmutable::now()->startOfMonth()->toDateString();
        $this->loadAssignedInputs();
    }

    private function budget(): Budget
    {
        return auth()->user()->currentBudget();
    }

    private function service(): BudgetService
    {
        return app(BudgetService::class);
    }

    private function loadAssignedInputs(): void
    {
        $inputs = [];
        foreach ($this->budget()->categoryGroups()->with('categories')->get() as $group) {
            foreach ($group->categories as $category) {
                $cents = $this->service()->assigned($this->budget(), $category, $this->month);
                $inputs[$category->id] = $cents === 0 ? '' : number_format($cents / 100, 2, '.', '');
            }
        }
        $this->assignedInputs = $inputs;
    }

    public function changeMonth(int $delta): void
    {
        $this->month = CarbonImmutable::parse($this->month)->addMonths($delta)->startOfMonth()->toDateString();
        $this->loadAssignedInputs();
        unset($this->groups, $this->readyToAssign);
    }

    public function updated(string $name, $value): void
    {
        if (! str_starts_with($name, 'assignedInputs.')) {
            return;
        }

        $categoryId = (int) substr($name, strlen('assignedInputs.'));

        $category = \App\Models\Category::query()
            ->whereHas('group', fn ($q) => $q->where('budget_id', $this->budget()->id))
            ->find($categoryId);

        if (! $category) {
            return;
        }

        $cents = $value === '' ? 0 : Money::toCents($value);
        $this->service()->assign($this->budget(), $category, $this->month, $cents);

        unset($this->groups, $this->readyToAssign);
    }

    #[Computed]
    public function readyToAssign(): int
    {
        return app(BudgetService::class)->readyToAssign($this->budget());
    }

    /**
     * Grupos con sus categorías y los montos calculados del mes.
     */
    #[Computed]
    public function groups(): Collection
    {
        $service = app(BudgetService::class);
        $budget = $this->budget();

        return $budget->categoryGroups()->with('categories')->orderBy('position')->get()
            ->map(function ($group) use ($service, $budget) {
                $categories = $group->categories->map(fn ($category) => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'activity' => $service->activity($budget, $category, $this->month),
                    'available' => $service->available($budget, $category, $this->month),
                ]);

                return [
                    'id' => $group->id,
                    'name' => $group->name,
                    'categories' => $categories,
                ];
            });
    }

    public function monthLabel(): string
    {
        $meses = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio',
            'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
        $date = CarbonImmutable::parse($this->month);

        return ucfirst($meses[(int) $date->format('n')]).' '.$date->format('Y');
    }

    public function fmt(int $cents): string
    {
        return Money::format($cents, $this->budget()->base_currency);
    }
}; ?>

<div class="space-y-4">
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

    {{-- Banner dinero por asignar --}}
    @php $rta = $this->readyToAssign; @endphp
    <div class="rounded-2xl p-5 text-white shadow-sm {{ $rta < 0 ? 'bg-red-600' : ($rta > 0 ? 'bg-emerald-600' : 'bg-slate-600') }}">
        <p class="text-sm opacity-90">Listo para asignar</p>
        <p class="mt-1 text-3xl font-bold tracking-tight">{{ $this->fmt($rta) }}</p>
        <p class="mt-2 text-xs opacity-90">
            @if ($rta > 0) Todavía tenés dinero sin asignar a una categoría.
            @elseif ($rta < 0) Asignaste más de lo que tenés. Sacá de alguna categoría.
            @else ¡Cada peso tiene un trabajo! 🎯
            @endif
        </p>
    </div>

    {{-- Categorías por grupo --}}
    <div class="space-y-4">
        @foreach ($this->groups as $group)
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                <div class="flex items-center justify-between bg-slate-50 px-4 py-2">
                    <h3 class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ $group['name'] }}</h3>
                    <span class="text-[10px] uppercase tracking-wide text-slate-400">Disponible</span>
                </div>
                <div class="divide-y divide-slate-100">
                    @foreach ($group['categories'] as $cat)
                        <div class="flex items-center gap-3 px-4 py-3" wire:key="cat-{{ $cat['id'] }}">
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-medium text-slate-800">{{ $cat['name'] }}</p>
                                <p class="text-xs text-slate-400">
                                    Gastado: {{ $this->fmt($cat['activity']) }}
                                </p>
                            </div>
                            <div class="w-24">
                                <div class="relative">
                                    <span class="pointer-events-none absolute left-2 top-1/2 -translate-y-1/2 text-xs text-slate-400">$</span>
                                    <input type="text" inputmode="decimal"
                                           wire:model.blur="assignedInputs.{{ $cat['id'] }}"
                                           placeholder="0,00"
                                           class="w-full rounded-lg border-slate-200 pl-5 pr-2 py-1.5 text-right text-sm focus:border-emerald-500 focus:ring-emerald-500">
                                </div>
                            </div>
                            <div class="w-20 text-right text-sm font-semibold {{ $cat['available'] < 0 ? 'text-red-600' : 'text-emerald-700' }}">
                                {{ $this->fmt($cat['available']) }}
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</div>
