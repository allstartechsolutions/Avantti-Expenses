<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One vendor's price for one line of the shared scope.
 */
class QuotationVendorItem extends Model
{
    protected $fillable = [
        'quotation_vendor_id',
        'quotation_item_id',
        'unit_price',
        'total_amount',
        'is_unavailable',
        'offered_brand',
        'offered_spec',
        'notes',
    ];

    protected $casts = [
        'is_unavailable' => 'boolean',
    ];

    public function quotationVendor(): BelongsTo
    {
        return $this->belongsTo(QuotationVendor::class);
    }

    public function quotationItem(): BelongsTo
    {
        return $this->belongsTo(QuotationItem::class);
    }

    // =========================================================================
    // ACCESSORS (Cents ↔ Currency)
    // =========================================================================

    protected function unitPrice(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => round($value / 100, 2),
            set: fn ($value) => round($value * 100),
        );
    }

    protected function totalAmount(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => round($value / 100, 2),
            set: fn ($value) => round($value * 100),
        );
    }

    /** A substitute is worth flagging on the map: it is not what was asked for. */
    public function isSubstitute(): bool
    {
        return filled($this->offered_brand) || filled($this->offered_spec);
    }
}
