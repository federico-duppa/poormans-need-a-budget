<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['budget_id', 'month'])]
class MonthlyBudget extends Model
{
    protected function casts(): array
    {
        return [
            'month' => 'date',
        ];
    }

    /** @return BelongsTo<Budget, $this> */
    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    /** @return HasMany<CategoryMonth, $this> */
    public function categoryMonths(): HasMany
    {
        return $this->hasMany(CategoryMonth::class);
    }
}
