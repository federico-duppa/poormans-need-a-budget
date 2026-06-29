<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'account_id', 'date', 'amount', 'currency', 'exchange_rate', 'amount_base',
    'payee_id', 'category_id', 'user_id', 'memo', 'cleared', 'has_splits', 'transfer_pair_id',
])]
class Transaction extends Model
{
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'amount' => 'integer',
            'amount_base' => 'integer',
            'exchange_rate' => 'decimal:10',
            'cleared' => 'boolean',
            'has_splits' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Keeps amount_base (amount in base currency) consistent on save.
        static::saving(function (Transaction $transaction) {
            $transaction->amount_base = $transaction->computeAmountBase();
        });
    }

    /**
     * Amount converted to the budget's base currency (cents).
     */
    public function computeAmountBase(): int
    {
        if ($this->exchange_rate !== null) {
            return (int) round($this->amount * (float) $this->exchange_rate);
        }

        return (int) $this->amount;
    }

    /** @return BelongsTo<Account, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /** @return BelongsTo<Payee, $this> */
    public function payee(): BelongsTo
    {
        return $this->belongsTo(Payee::class);
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** Author of the transaction within the family group. @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<TransactionSplit, $this> */
    public function splits(): HasMany
    {
        return $this->hasMany(TransactionSplit::class);
    }

    /** @return BelongsTo<Transaction, $this> */
    public function transferPair(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'transfer_pair_id');
    }

    public function isInflow(): bool
    {
        return $this->amount > 0;
    }

    public function isTransfer(): bool
    {
        return $this->transfer_pair_id !== null;
    }

    /**
     * First day of the budget month it belongs to (YYYY-MM-01).
     */
    public function budgetMonth(): string
    {
        return $this->date->copy()->startOfMonth()->toDateString();
    }

    public function formattedAmount(): string
    {
        return Money::format($this->amount, $this->currency);
    }
}
