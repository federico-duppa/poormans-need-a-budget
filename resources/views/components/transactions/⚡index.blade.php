<?php

use App\Models\Transaction;
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
}; ?>

<div class="space-y-4">
    @if (session('status'))
        <div class="rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-700 ring-1 ring-emerald-200">
            {{ session('status') }}
        </div>
    @endif

    {{-- Filtros --}}
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

    {{-- Lista --}}
    <div class="space-y-2">
        @forelse ($this->transactions as $tx)
            <div class="flex items-center justify-between rounded-2xl border border-slate-200 bg-white p-4">
                <div class="min-w-0">
                    <p class="truncate font-medium text-slate-800">
                        {{ $tx->payee?->name ?? ($tx->category?->name ?? 'Movimiento') }}
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
