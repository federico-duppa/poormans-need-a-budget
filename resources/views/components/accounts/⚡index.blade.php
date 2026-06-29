<?php

use App\Models\Account;
use App\Services\BudgetService;
use App\Support\Money;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Validate;
use Livewire\Component;

new class extends Component
{
    public bool $showForm = false;

    // Pago de tarjeta
    public ?int $payingCardId = null;
    public ?int $payFromId = null;
    public string $payAmount = '';
    public string $payDate = '';

    #[Validate('required|string|max:60')]
    public string $name = '';

    #[Validate('required|in:cash,checking,credit_card')]
    public string $type = 'checking';

    #[Validate('required|in:ARS,USD')]
    public string $currency = 'ARS';

    public function types(): array
    {
        return Account::TYPES;
    }

    public function currencies(): array
    {
        return array_keys(config('budget.currencies'));
    }

    #[Computed]
    public function accounts(): Collection
    {
        return auth()->user()->currentBudget()
            ->accounts()->active()->orderBy('position')->orderBy('name')->get();
    }

    public function save(): void
    {
        $this->validate();

        $budget = auth()->user()->currentBudget();

        $budget->accounts()->create([
            'name' => $this->name,
            'type' => $this->type,
            'currency' => $this->currency,
            // Las tarjetas de crédito también son cuentas on-budget.
            'on_budget' => true,
            'position' => $budget->accounts()->count(),
        ]);

        $this->reset(['name', 'showForm']);
        $this->type = 'checking';
        $this->currency = 'ARS';

        unset($this->accounts);
        $this->dispatch('account-created');
    }

    #[Computed]
    public function cashAccounts(): Collection
    {
        return $this->accounts->where('type', '!=', 'credit_card');
    }

    public function startPay(int $cardId): void
    {
        $this->payingCardId = $cardId;
        $this->payFromId = $this->cashAccounts->first()?->id;
        $this->payAmount = '';
        $this->payDate = now()->toDateString();
    }

    public function payCard(BudgetService $service): void
    {
        $this->validate([
            'payFromId' => ['required', 'integer'],
            'payAmount' => ['required'],
            'payDate' => ['required', 'date'],
        ]);

        $budget = auth()->user()->currentBudget();
        $card = $budget->accounts()->findOrFail($this->payingCardId);
        $from = $budget->accounts()->findOrFail($this->payFromId);

        $cents = Money::toCents($this->payAmount);
        if ($cents <= 0) {
            $this->addError('payAmount', 'Ingresá un monto mayor a cero.');

            return;
        }

        $service->payCreditCard($from, $card, $cents, $this->payDate, auth()->id());

        $this->reset(['payingCardId', 'payAmount']);
        unset($this->accounts);
        session()->flash('status', 'Pago registrado.');
    }
}; ?>

<div class="space-y-4">
    <div class="flex items-center justify-between">
        <h2 class="text-base font-semibold text-slate-700">Tus cuentas</h2>
        <button wire:click="$toggle('showForm')"
                class="rounded-full bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">
            {{ $showForm ? 'Cancelar' : '+ Nueva' }}
        </button>
    </div>

    @if ($showForm)
        <form wire:submit="save" class="space-y-3 rounded-2xl border border-slate-200 bg-white p-4">
            <div>
                <label class="mb-1 block text-xs font-medium text-slate-500">Nombre</label>
                <input type="text" wire:model="name" placeholder="Ej: Banco Galicia"
                       class="w-full rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500">Tipo</label>
                    <select wire:model="type" class="w-full rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                        @foreach ($this->types() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-500">Moneda</label>
                    <select wire:model="currency" class="w-full rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                        @foreach ($this->currencies() as $code)
                            <option value="{{ $code }}">{{ $code }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <button type="submit"
                    class="w-full rounded-lg bg-emerald-600 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700">
                Crear cuenta
            </button>
        </form>
    @endif

    @if (session('status'))
        <div class="rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-700 ring-1 ring-emerald-200">
            {{ session('status') }}
        </div>
    @endif

    <div class="space-y-2">
        @forelse ($this->accounts as $account)
            <div class="rounded-2xl border border-slate-200 bg-white p-4" wire:key="acc-{{ $account->id }}">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="font-medium text-slate-800">{{ $account->name }}</p>
                        <p class="text-xs text-slate-400">{{ $account->typeLabel() }} · {{ $account->currency }}</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <p class="font-semibold {{ $account->balance() < 0 ? 'text-red-600' : 'text-slate-700' }}">
                            {{ $account->formattedBalance() }}
                        </p>
                        @if ($account->isCreditCard())
                            <button wire:click="startPay({{ $account->id }})"
                                    class="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-200">
                                Pagar
                            </button>
                        @endif
                    </div>
                </div>

                @if ($payingCardId === $account->id)
                    <form wire:submit="payCard" class="mt-3 space-y-2 border-t border-slate-100 pt-3">
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-500">Desde</label>
                                <select wire:model="payFromId" class="w-full rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                                    @foreach ($this->cashAccounts as $acc)
                                        <option value="{{ $acc->id }}">{{ $acc->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-slate-500">Monto</label>
                                <input type="text" inputmode="decimal" wire:model="payAmount" placeholder="0,00"
                                       class="w-full rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                                @error('payAmount') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <button type="submit" class="flex-1 rounded-lg bg-emerald-600 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                                Registrar pago
                            </button>
                            <button type="button" wire:click="$set('payingCardId', null)" class="rounded-lg bg-slate-100 px-3 text-sm text-slate-600 hover:bg-slate-200">
                                Cancelar
                            </button>
                        </div>
                    </form>
                @endif
            </div>
        @empty
            <p class="rounded-2xl border border-dashed border-slate-300 bg-white p-6 text-center text-sm text-slate-400">
                Todavía no tenés cuentas. Creá la primera para empezar a cargar movimientos.
            </p>
        @endforelse
    </div>
</div>
