<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One cost code's share of a change order. The amount is signed: positive adds
 * budget to the code, negative takes it away.
 */
class ChangeOrderItem extends Model
{
    protected $fillable = [
        'change_order_id',
        'budget_item_id',
        'description',
        'amount',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    /**
     * Get/Set amount as dollars (stored as signed cents).
     */
    protected function amount(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => round($value / 100, 2),
            set: fn ($value) => round($value * 100),
        );
    }

    public function changeOrder(): BelongsTo
    {
        return $this->belongsTo(ChangeOrder::class);
    }

    public function budgetItem(): BelongsTo
    {
        return $this->belongsTo(BudgetItem::class);
    }

    /**
     * Get the cost code display string. A line with no code falls into the
     * budget's default bucket, the same as an uncoded expense.
     */
    public function getCostCodeDisplayAttribute(): string
    {
        if ($this->budgetItem) {
            return $this->budgetItem->code . ' - ' . $this->budgetItem->name;
        }

        return __('Unassigned');
    }

    /**
     * Whether this line takes budget away from its cost code.
     */
    public function isDeductive(): bool
    {
        return $this->amount < 0;
    }
}
