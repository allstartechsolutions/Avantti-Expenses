<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceEmail extends Model
{
    protected $fillable = [
        'invoice_id',
        'sent_to',
        'cc',
        'subject',
        'body',
        'sent_by',
        'sent_at',
        'opened_at',
        'tracking_token',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'opened_at' => 'datetime',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}
