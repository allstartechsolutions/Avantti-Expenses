<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractChangeOrder extends Model
{
    protected $fillable = [
        'contract_id',
        'title',
        'date',
        'amount',
        'budget_item_id',
        'description',
        'file_path',
        'created_by',
    ];

    protected $casts = [
        'date' => 'date',
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

    /** An aditivo has no project of its own; it inherits its contract's. */
    public function permissionScope(): ?Contract
    {
        return $this->relationLoaded('contract') ? $this->contract : $this->contract()->first();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function budgetItem(): BelongsTo
    {
        return $this->belongsTo(BudgetItem::class);
    }

    /**
     * Get the cost code display string. A change order without its own
     * cost code follows the contract's allocation ("Unassigned" bucket).
     */
    public function getCostCodeDisplayAttribute(): string
    {
        if ($this->budgetItem) {
            return $this->budgetItem->code . ' - ' . $this->budgetItem->name;
        }

        return 'Unassigned';
    }
}
