<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One recorded round of negotiation with one vendor.
 */
class QuotationNegotiation extends Model
{
    protected $fillable = [
        'quotation_vendor_id',
        'round',
        'previous_total',
        'new_total',
        'note',
        'negotiated_by',
        'negotiated_at',
    ];

    protected $casts = [
        'round' => 'integer',
        'negotiated_at' => 'datetime',
    ];

    public function quotationVendor(): BelongsTo
    {
        return $this->belongsTo(QuotationVendor::class);
    }

    public function negotiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'negotiated_by');
    }

    protected function previousTotal(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => round($value / 100, 2),
            set: fn ($value) => round($value * 100),
        );
    }

    protected function newTotal(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => round($value / 100, 2),
            set: fn ($value) => round($value * 100),
        );
    }

    /**
     * Positive when the vendor came down, which is the point of the round.
     * Not named saving() — that collides with Eloquent's model event hook.
     */
    public function savingAmount(): float
    {
        return round((float) $this->previous_total - (float) $this->new_total, 2);
    }
}
