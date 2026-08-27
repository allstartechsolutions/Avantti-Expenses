<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BudgetItem extends Model
{
    protected $fillable = [
        'budget_id',
        'parent_id',
        'code',
        'name',
        'description',
        'budgeted_amount',
        'sort_order',
        'is_default',
        'requires_approval',
        'default_approval_type',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_default' => 'boolean',
        'requires_approval' => 'boolean',
    ];

    /**
     * Get the budget that owns this item.
     */
    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    /**
     * Get the parent budget item (self-reference).
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(BudgetItem::class, 'parent_id');
    }

    /**
     * Get the child budget items.
     */
    public function children(): HasMany
    {
        return $this->hasMany(BudgetItem::class, 'parent_id')->orderBy('sort_order');
    }

    /**
     * Get/Set budgeted_amount as dollars (stored as cents).
     */
    protected function budgetedAmount(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => round($value / 100, 2),
            set: fn ($value) => round($value * 100),
        );
    }

    /**
     * Expense lines coded to this cost code (the actual cost).
     */
    public function expenseItems(): HasMany
    {
        return $this->hasMany(ExpenseItem::class);
    }

    /**
     * Purchase order lines coded to this cost code.
     */
    public function purchaseOrderItems(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    /**
     * Project / job site change order lines that revise this cost code.
     */
    public function changeOrderItems(): HasMany
    {
        return $this->hasMany(ChangeOrderItem::class);
    }

    /**
     * Contract amounts allocated to this cost code.
     */
    public function contractAllocations(): HasMany
    {
        return $this->hasMany(ContractBudgetAllocation::class);
    }

    /**
     * Subcontract change orders booked against this cost code.
     */
    public function contractChangeOrders(): HasMany
    {
        return $this->hasMany(ContractChangeOrder::class);
    }

    /**
     * Check if this item is a parent (has no parent_id).
     */
    public function isParent(): bool
    {
        return is_null($this->parent_id);
    }

    /**
     * Check if this item is a child (has a parent_id).
     */
    public function isChild(): bool
    {
        return !is_null($this->parent_id);
    }

    /**
     * Check if this item has children.
     */
    public function hasChildren(): bool
    {
        return $this->children()->exists();
    }

    /**
     * Get the full code path (parent code + this code).
     */
    public function getFullCodeAttribute(): string
    {
        if ($this->parent) {
            return $this->parent->code . '.' . $this->code;
        }

        return $this->code;
    }

    /**
     * Get the full name path (parent name > this name).
     */
    public function getFullNameAttribute(): string
    {
        if ($this->parent) {
            return $this->parent->name . ' > ' . $this->name;
        }

        return $this->name;
    }
}
