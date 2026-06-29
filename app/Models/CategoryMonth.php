<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['monthly_budget_id', 'category_id', 'assigned'])]
class CategoryMonth extends Model
{
    protected function casts(): array
    {
        return [
            'assigned' => 'integer',
        ];
    }

    /** @return BelongsTo<MonthlyBudget, $this> */
    public function monthlyBudget(): BelongsTo
    {
        return $this->belongsTo(MonthlyBudget::class);
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
