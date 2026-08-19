<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One RFQ e-mail the system sent for a round — kept per vendor, failures
 * included, so what each vendor received (and when) is on the record.
 */
class QuotationRfqEmail extends Model
{
    protected $fillable = [
        'quotation_id',
        'quotation_vendor_id',
        'sent_to',
        'cc',
        'subject',
        'body',
        'status',
        'error',
        'sent_by',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function quotationVendor(): BelongsTo
    {
        return $this->belongsTo(QuotationVendor::class);
    }

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function failed(): bool
    {
        return $this->status === 'failed';
    }
}
