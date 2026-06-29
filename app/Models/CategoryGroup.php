<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['budget_id', 'name', 'position', 'is_system'])]
class CategoryGroup extends Model
{
    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    /** @return BelongsTo<Budget, $this> */
    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    /** Categorías activas (no archivadas). @return HasMany<Category, $this> */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class)->whereNull('archived_at')->orderBy('position');
    }

    /** Todas las categorías, incluidas las archivadas. @return HasMany<Category, $this> */
    public function allCategories(): HasMany
    {
        return $this->hasMany(Category::class)->orderBy('position');
    }
}
