<?php

use App\Models\Account;
use App\Models\Payee;
use App\Models\Transaction;
use App\Support\Money;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public ?int $account_id = null;
    public string $direction = 'outflow'; // outflow | inflow
    public string $amount = '';
    public string $date = '';
    public string $payee = '';
    public ?int $category_id = null;
    public string $memo = '';
    public string $exchange_rate = '';

    public function mount(): void
    {
        $this->date = now()->toDateString();
        $this->account_id = $this->accounts()->first()?->id;
    }

    protected function rules(): array
    {
        return [
            'account_id' => ['required', 'integer'],
            'direction' => ['required', 'in:outflow,inflow'],
            'amount' => ['required', 'string'],
            'date' => ['required', 'date'],
            'payee' => ['nullable', 'string', 'max:80'],
            'category_id' => ['nullable', 'integer'],
            'memo' => ['nullable', 'string', 'max:120'],
            'exchange_rate' => ['nullable', 'numeric', 'gt:0'],
        ];
    }

    #[Computed]
    public function accounts(): Collection
    {
        return auth()->user()->currentBudget()
            ->accounts()->active()->orderBy('name')->get();
    }

    #[Computed]
    public function categoryGroups(): Collection
    {
        return auth()->user()->currentBudget()
            ->categoryGroups()->with('categories')->orderBy('position')->get();
    }

    #[Computed]
    public function selectedAccount(): ?Account
    {
        return $this->accounts->firstWhere('id', $this->account_id);
    }

    #[Computed]
    public function needsExchangeRate(): bool
    {
        $account = $this->selectedAccount;

        return $account !== null
            && $account->currency !== config('budget.base_currency');
    }

    public function save()
    {
        $validated = $this->validate();

        $budget = auth()->user()->currentBudget();

        $account = $budget->accounts()->active()->findOrFail($validated['account_id']);

        $cents = Money::toCents($validated['amount']);
        if ($cents <= 0) {
            $this->addError('amount', 'Ingresá un monto mayor a cero.');

            return null;
        }

        // Sign: outflow negative, inflow positive.
        $signedAmount = $this->direction === 'outflow' ? -$cents : $cents;

        $payeeId = null;
        if (filled($validated['payee'])) {
            $payeeId = Payee::firstOrCreate([
                'budget_id' => $budget->id,
                'name' => trim($validated['payee']),
            ])->id;
        }

        $rate = null;
        if ($this->needsExchangeRate && filled($validated['exchange_rate'])) {
            $rate = (float) $validated['exchange_rate'];
        }

        Transaction::create([
            'account_id' => $account->id,
            'date' => $validated['date'],
            'amount' => $signedAmount,
            'currency' => $account->currency,
            'exchange_rate' => $rate,
            'payee_id' => $payeeId,
            // Income without a category goes to "Listo para asignar".
            'category_id' => $this->direction === 'outflow' ? $validated['category_id'] : null,
            'user_id' => auth()->id(),
            'memo' => $validated['memo'] ?: null,
            'cleared' => true,
        ]);

        session()->flash('status', 'Movimiento guardado.');

        return redirect()->route('transactions');
    }
}; ?>

<div class="space-y-4">
    <form wire:submit="save" class="space-y-4">
        {{-- Income / expense selector --}}
        <div class="grid grid-cols-2 gap-2 rounded-xl bg-slate-100 p-1">
            <button type="button" wire:click="$set('direction', 'outflow')"
                    class="rounded-lg py-2 text-sm font-semibold {{ $direction === 'outflow' ? 'bg-white text-red-600 shadow' : 'text-slate-500' }}">
                Gasto
            </button>
            <button type="button" wire:click="$set('direction', 'inflow')"
                    class="rounded-lg py-2 text-sm font-semibold {{ $direction === 'inflow' ? 'bg-white text-emerald-600 shadow' : 'text-slate-500' }}">
                Ingreso
            </button>
        </div>

        {{-- Amount --}}
        <div>
            <label class="mb-1 block text-xs font-medium text-slate-500">Monto</label>
            <input type="text" inputmode="decimal" wire:model="amount" placeholder="0,00"
                   class="w-full rounded-lg border-slate-300 text-2xl font-semibold focus:border-emerald-500 focus:ring-emerald-500">
            @error('amount') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        {{-- Account --}}
        <div>
            <label class="mb-1 block text-xs font-medium text-slate-500">Cuenta</label>
            <select wire:model.live="account_id" class="w-full rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                @foreach ($this->accounts as $account)
                    <option value="{{ $account->id }}">{{ $account->name }} ({{ $account->currency }})</option>
                @endforeach
            </select>
            @error('account_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        </div>

        {{-- Exchange rate (only if the account is not in the base currency) --}}
        @if ($this->needsExchangeRate)
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-500">
                    Tipo de cambio (1 {{ $this->selectedAccount->currency }} = ? {{ config('budget.base_currency') }})
                </label>
                <input type="text" inputmode="decimal" wire:model="exchange_rate" placeholder="Ej: 1000"
                       class="w-full rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                @error('exchange_rate') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
        @endif

        {{-- Payee --}}
        <div>
            <label class="mb-1 block text-xs font-medium text-slate-500">Beneficiario / lugar</label>
            <input type="text" wire:model="payee" placeholder="Ej: Coto, Sueldo, YPF"
                   class="w-full rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
        </div>

        {{-- Category (expenses only) --}}
        @if ($direction === 'outflow')
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-500">Categoría</label>
                <select wire:model="category_id" class="w-full rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                    <option value="">— Sin categoría —</option>
                    @foreach ($this->categoryGroups as $group)
                        <optgroup label="{{ $group->name }}">
                            @foreach ($group->categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>
        @endif

        {{-- Date + memo --}}
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-500">Fecha</label>
                <input type="date" wire:model="date"
                       class="w-full rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
            </div>
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-500">Nota</label>
                <input type="text" wire:model="memo" placeholder="Opcional"
                       class="w-full rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
            </div>
        </div>

        <button type="submit"
                class="w-full rounded-xl bg-emerald-600 py-3 text-sm font-semibold text-white hover:bg-emerald-700">
            Guardar movimiento
        </button>
    </form>
</div>
