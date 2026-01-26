<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseItem extends Model
{
    protected $fillable = [
        'expense_id',
        'budget_item_id',
        'catalog_item_id',
        'item_name',
        'item_type',
        'description',
        'quantity',
        'unit',
        'unit_price',
        'total_amount',
        'sort_order',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    /**
     * Get the expense this item belongs to.
     */
    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    /**
     * Get the budget item (cost code) this expense item is assigned to.
     */
    public function budgetItem(): BelongsTo
    {
        return $this->belongsTo(BudgetItem::class);
    }

    /**
     * Get the catalog item (if from catalog).
     */
    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class);
    }

    /**
     * Get/Set unit_price as dollars (stored as cents).
     */
    protected function unitPrice(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => round($value / 100, 2),
            set: fn ($value) => round($value * 100),
        );
    }

    /**
     * Get/Set total_amount as dollars (stored as cents).
     */
    protected function totalAmount(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => round($value / 100, 2),
            set: fn ($value) => round($value * 100),
        );
    }

    /**
     * Check if this is a custom (non-catalog) item.
     */
    public function isCustom(): bool
    {
        return $this->item_type === 'custom' || is_null($this->catalog_item_id);
    }

    /**
     * Calculate and return the total (quantity * unit_price).
     */
    public function calculateTotal(): float
    {
        return round($this->quantity * $this->unit_price, 2);
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
