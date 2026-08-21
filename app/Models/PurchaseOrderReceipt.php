<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One delivery against a purchase order.
 *
 * A part-delivery is the normal case on site, so an order can have several of
 * these; together they add up to what has actually arrived.
 */
class PurchaseOrderReceipt extends Model
{
    protected $fillable = [
        'purchase_order_id',
        'received_at',
        'received_by',
        'note',
    ];

    protected $casts = [
        'received_at' => 'date',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderReceiptLine::class);
    }

    /** Questions about a delivery are questions about its order. */
    public function permissionScope(): ?PurchaseOrder
    {
        return $this->relationLoaded('purchaseOrder')
            ? $this->purchaseOrder
            : $this->purchaseOrder()->first();
    }
}
