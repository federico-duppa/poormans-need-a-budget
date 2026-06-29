<?php

namespace App\Models;

use App\Observers\AccountObserver;
use App\Support\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy(AccountObserver::class)]
#[Fillable(['budget_id', 'name', 'type', 'currency', 'on_budget', 'position', 'archived_at'])]
class Account extends Model
{
    public const TYPES = [
        'cash' => 'Efectivo',
        'checking' => 'Cuenta bancaria',
        'credit_card' => 'Tarjeta de crédito',
    ];

    protected function casts(): array
    {
        return [
            'on_budget' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Budget, $this> */
    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    /** @return HasMany<Transaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /** Associated payment category (credit cards only). @return HasMany<Category, $this> */
    public function paymentCategory()
    {
        return $this->hasOne(Category::class, 'linked_account_id');
    }

    /** @param  Builder<Account>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('archived_at');
    }

    public function isCreditCard(): bool
    {
        return $this->type === 'credit_card';
    }

    /**
     * Account balance in cents (sum of all its transactions).
     */
    public function balance(): int
    {
        return (int) $this->transactions()->sum('amount');
    }

    public function formattedBalance(): string
    {
        return Money::format($this->balance(), $this->currency);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}
