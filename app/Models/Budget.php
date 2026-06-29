<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'base_currency'])]
class Budget extends Model
{
    /**
     * Miembros (usuarios) del presupuesto familiar.
     *
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    /** @return HasMany<Account, $this> */
    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    /** @return HasMany<CategoryGroup, $this> */
    public function categoryGroups(): HasMany
    {
        return $this->hasMany(CategoryGroup::class);
    }

    /** @return HasMany<Payee, $this> */
    public function payees(): HasMany
    {
        return $this->hasMany(Payee::class);
    }
}
