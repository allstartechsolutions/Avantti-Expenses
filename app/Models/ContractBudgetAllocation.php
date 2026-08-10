<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractBudgetAllocation extends Model
{
    protected $fillable = [
        'contract_id',
        'budget_item_id',
        'amount',
    ];

    protected function amount(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => round($value / 100, 2),
            set: fn ($value) => round($value * 100),
        );
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function budgetItem(): BelongsTo
    {
        return $this->belongsTo(BudgetItem::class);
    }

    /**
     * Get the cost code display string.
     */
    public function getCostCodeDisplayAttribute(): string
    {
        if ($this->budgetItem) {
            return $this->budgetItem->code . ' - ' . $this->budgetItem->name;
        }

        return 'Unassigned';
    }
}
