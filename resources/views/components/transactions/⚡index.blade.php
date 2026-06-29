<?php

use App\Models\Payee;
use App\Models\Transaction;
use App\Support\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    #[Url]
    public string $account = '';

    #[Url]
    public string $member = '';

    // Inline edit state for the currently open transaction.
    public ?int $editingId = null;
    public string $e_direction = 'outflow';
    public string $e_amount = '';
    public ?int $e_account_id = null;
    public ?int $e_category_id = null;
    public string $e_payee = '';
    public string $e_memo = '';
    public string $e_date = '';
    public string $e_exchange_rate = '';

    private function budget()
    {
        return auth()->user()->currentBudget();
    }

    #[Computed]
    public function accounts(): Collection
    {
        return $this->budget()->accounts()->active()->orderBy('name')->get();
    }

    #[Computed]
    public function members(): Collection
    {
        return $this->budget()->users()->orderBy('name')->get();
    }

    #[Computed]
    public function categoryGroups(): Collection
    {
        return $this->budget()->categoryGroups()->with('categories')->orderBy('position')->get();
    }

    #[Computed]
    public function transactions(): LengthAwarePaginator
    {
        $accountIds = $this->budget()->accounts()->pluck('id');

        return Transaction::query()
            ->whereIn('account_id', $accountIds)
            ->when($this->account !== '', fn ($q) => $q->where('account_id', $this->account))
            ->when($this->member !== '', fn ($q) => $q->where('user_id', $this->member))
            ->with(['account', 'payee', 'category', 'user'])
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate(20);
    }

    public function updating($name): void
    {
        if (in_array($name, ['account', 'member'], true)) {
            $this->resetPage();
        }
    }

    /** Load a transaction into the inline edit form. */
    public function edit(int $id): void
    {
        $tx = $this->ownedTransaction($id);
        if (! $tx) {
            return;
        }

        $this->editingId = $tx->id;
        $this->e_direction = $tx->amount < 0 ? 'outflow' : 'inflow';
        $this->e_amount = number_format(abs($tx->amount) / 100, 2, '.', '');
        $this->e_account_id = $tx->account_id;
        $this->e_category_id = $tx->category_id;
        $this->e_payee = $tx->payee?->name ?? '';
        $this->e_memo = $tx->memo ?? '';
        $this->e_date = $tx->date->toDateString();
        $this->e_exchange_rate = $tx->exchange_rate !== null ? (string) (float) $tx->exchange_rate : '';
    }

    public function cancelEdit(): void
    {
        $this->reset(['editingId', 'e_amount', 'e_payee', 'e_memo', 'e_exchange_rate']);
    }

    #[Computed]
    public function editAccount()
    {
        return $this->accounts->firstWhere('id', $this->e_account_id);
    }

    #[Computed]
    public function editNeedsRate(): bool
    {
        return $this->editAccount !== null
            && $this->editAccount->currency !== config('budget.base_currency');
    }

    public function saveEdit(): void
    {
        $tx = $this->ownedTransaction($this->editingId);
        if (! $tx || $tx->isTransfer()) {
            return; // transfers are not field-editable
        }

        $this->validate([
            'e_account_id' => ['required', 'integer'],
            'e_amount' => ['required'],
            'e_date' => ['required', 'date'],
            'e_payee' => ['nullable', 'string', 'max:80'],
            'e_memo' => ['nullable', 'string', 'max:120'],
            'e_exchange_rate' => ['nullable', 'numeric', 'gt:0'],
        ]);

        $account = $this->budget()->accounts()->active()->findOrFail($this->e_account_id);

        $cents = Money::toCents($this->e_amount);
        if ($cents <= 0) {
            $this->addError('e_amount', 'Ingresá un monto mayor a cero.');

            return;
        }

        $payeeId = null;
        if (filled($this->e_payee)) {
            $payeeId = Payee::firstOrCreate([
                'budget_id' => $this->budget()->id,
                'name' => trim($this->e_payee),
            ])->id;
        }

        $rate = null;
        if ($account->currency !== config('budget.base_currency') && filled($this->e_exchange_rate)) {
            $rate = (float) $this->e_exchange_rate;
        }

        $tx->update([
            'account_id' => $account->id,
            'date' => $this->e_date,
            'amount' => $this->e_direction === 'outflow' ? -$cents : $cents,
            'currency' => $account->currency,
            'exchange_rate' => $rate,
            'payee_id' => $payeeId,
            'category_id' => $this->e_direction === 'outflow' ? $this->e_category_id : null,
            'memo' => $this->e_memo ?: null,
        ]);

        $this->cancelEdit();
        session()->flash('status', 'Movimiento actualizado.');
    }

    /** Delete a transaction; if it is a transfer, remove both legs. */
    public function delete(int $id): void
    {
        $tx = $this->ownedTransaction($id);
        if (! $tx) {
            return;
        }

        $tx->transferPair?->delete();
        $tx->delete();

        if ($this->editingId === $id) {
            $this->cancelEdit();
        }

        session()->flash('status', 'Movimiento eliminado.');
    }

    /** Fetch a transaction that belongs to one of the budget's accounts. */
    private function ownedTransaction(?int $id): ?Transaction
    {
        if ($id === null) {
            return null;
        }

        return Transaction::query()
            ->whereIn('account_id', $this->budget()->accounts()->pluck('id'))
            ->find($id);
    }
}; ?>

<div class="space-y-4">
    @if (session('status'))
        <div class="rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-700 ring-1 ring-emerald-200">
            {{ session('status') }}
        </div>
    @endif

    {{-- Filters --}}
    <div class="grid grid-cols-2 gap-2">
        <select wire:model.live="account" class="rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
            <option value="">Todas las cuentas</option>
            @foreach ($this->accounts as $acc)
                <option value="{{ $acc->id }}">{{ $acc->name }}</option>
            @endforeach
        </select>
        <select wire:model.live="member" class="rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
            <option value="">Todos los miembros</option>
            @foreach ($this->members as $m)
                <option value="{{ $m->id }}">{{ $m->name }}</option>
            @endforeach
        </select>
    </div>

    {{-- List --}}
    <div class="space-y-2">
        @forelse ($this->transactions as $tx)
            <div class="rounded-2xl border border-slate-200 bg-white" wire:key="tx-{{ $tx->id }}">
                <button type="button" wire:click="edit({{ $tx->id }})" class="flex w-full items-center justify-between p-4 text-left">
                    <div class="min-w-0">
                        <p class="truncate font-medium text-slate-800">
                            {{ $tx->payee?->name ?? ($tx->category?->name ?? 'Movimiento') }}
                            @if ($tx->isTransfer()) <span class="text-xs font-normal text-slate-400">· transferencia</span> @endif
                        </p>
                        <p class="truncate text-xs text-slate-400">
                            {{ $tx->date->format('d/m/Y') }} · {{ $tx->account->name }}
                            @if ($tx->category) · {{ $tx->category->name }} @endif
                            @if ($tx->user) · {{ $tx->user->name }} @endif
                        </p>
                    </div>
                    <p class="ml-3 shrink-0 font-semibold {{ $tx->isInflow() ? 'text-emerald-600' : 'text-slate-700' }}">
                        {{ $tx->formattedAmount() }}
                    </p>
                </button>

                @if ($editingId === $tx->id)
                    <div class="border-t border-slate-100 p-4">
                        @if ($tx->isTransfer())
                            <p class="mb-3 text-xs text-slate-500">
                                Es una transferencia (pago de tarjeta). Solo se puede eliminar (borra ambas patas).
                            </p>
                        @else
                            <div class="space-y-3">
                                <div class="grid grid-cols-2 gap-2 rounded-xl bg-slate-100 p-1">
                                    <button type="button" wire:click="$set('e_direction', 'outflow')"
                                            class="rounded-lg py-1.5 text-sm font-semibold {{ $e_direction === 'outflow' ? 'bg-white text-red-600 shadow' : 'text-slate-500' }}">Gasto</button>
                                    <button type="button" wire:click="$set('e_direction', 'inflow')"
                                            class="rounded-lg py-1.5 text-sm font-semibold {{ $e_direction === 'inflow' ? 'bg-white text-emerald-600 shadow' : 'text-slate-500' }}">Ingreso</button>
                                </div>

                                <div class="grid grid-cols-2 gap-2">
                                    <input type="text" inputmode="decimal" wire:model="e_amount" placeholder="0,00"
                                           class="rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                                    <input type="date" wire:model="e_date"
                                           class="rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                                </div>
                                @error('e_amount') <p class="text-xs text-red-600">{{ $message }}</p> @enderror

                                <select wire:model.live="e_account_id" class="w-full rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                                    @foreach ($this->accounts as $acc)
                                        <option value="{{ $acc->id }}">{{ $acc->name }} ({{ $acc->currency }})</option>
                                    @endforeach
                                </select>

                                @if ($this->editNeedsRate)
                                    <input type="text" inputmode="decimal" wire:model="e_exchange_rate"
                                           placeholder="Tipo de cambio a {{ config('budget.base_currency') }}"
                                           class="w-full rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                                @endif

                                <input type="text" wire:model="e_payee" placeholder="Beneficiario / lugar"
                                       class="w-full rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">

                                @if ($e_direction === 'outflow')
                                    <select wire:model="e_category_id" class="w-full rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">
                                        <option value="">— Sin categoría —</option>
                                        @foreach ($this->categoryGroups as $group)
                                            <optgroup label="{{ $group->name }}">
                                                @foreach ($group->categories as $category)
                                                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                                                @endforeach
                                            </optgroup>
                                        @endforeach
                                    </select>
                                @endif

                                <input type="text" wire:model="e_memo" placeholder="Nota (opcional)"
                                       class="w-full rounded-lg border-slate-300 text-sm focus:border-emerald-500 focus:ring-emerald-500">

                                <button type="button" wire:click="saveEdit"
                                        class="w-full rounded-lg bg-emerald-600 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Guardar cambios</button>
                            </div>
                        @endif

                        <div class="mt-3 flex gap-2">
                            <button type="button" wire:click="delete({{ $tx->id }})" wire:confirm="¿Eliminar este movimiento?"
                                    class="flex-1 rounded-lg bg-red-50 py-2 text-sm font-semibold text-red-600 hover:bg-red-100">Eliminar</button>
                            <button type="button" wire:click="cancelEdit"
                                    class="rounded-lg bg-slate-100 px-4 text-sm text-slate-600 hover:bg-slate-200">Cancelar</button>
                        </div>
                    </div>
                @endif
            </div>
        @empty
            <p class="rounded-2xl border border-dashed border-slate-300 bg-white p-6 text-center text-sm text-slate-400">
                No hay movimientos todavía.
            </p>
        @endforelse
    </div>

    <div>
        {{ $this->transactions->links() }}
    </div>
</div>
