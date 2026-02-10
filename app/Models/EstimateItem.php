<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EstimateItem extends Model
{
    protected $fillable = [
        'estimate_id',
        'catalog_item_id',
        'item_type',
        'item_name',
        'description',
        'quantity',
        'unit',
        'unit_price',
        'discount_type',
        'discount_value',
        'discount_amount',
        'is_taxable',
        'tax_rate',
        'tax_amount',
        'total_amount',
        'sort_order',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'discount_value' => 'decimal:2',
        'is_taxable' => 'boolean',
        'tax_rate' => 'decimal:4',
        'sort_order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Money accessors (cents <-> dollars)

    protected function unitPrice(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => round($value / 100, 2),
            set: fn ($value) => round($value * 100),
        );
    }

    protected function discountAmount(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => round($value / 100, 2),
            set: fn ($value) => round($value * 100),
        );
    }

    protected function taxAmount(): Attribute
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

    // Relationships

    public function estimate(): BelongsTo
    {
        return $this->belongsTo(Estimate::class);
    }

    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class);
    }

    // Helpers

    public function isCustom(): bool
    {
        return $this->item_type === 'custom';
    }
}
