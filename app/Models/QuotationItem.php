<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of the shared scope. Every invited vendor prices this same list,
 * which is what makes the comparison map honest.
 */
class QuotationItem extends Model
{
    protected $fillable = [
        'quotation_id',
        'awarded_quotation_vendor_id',
        'purchase_requisition_item_id',
        'catalog_item_id',
        'budget_item_id',
        'item_name',
        'item_type',
        'description',
        'quantity',
        'unit',
        'sort_order',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    /** The proposal that won this line, on a split award. */
    public function awardedVendorRow(): BelongsTo
    {
        return $this->belongsTo(QuotationVendor::class, 'awarded_quotation_vendor_id');
    }

    public function requisitionItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequisitionItem::class, 'purchase_requisition_item_id');
    }

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class);
    }

    public function budgetItem(): BelongsTo
    {
        return $this->belongsTo(BudgetItem::class);
    }
}
