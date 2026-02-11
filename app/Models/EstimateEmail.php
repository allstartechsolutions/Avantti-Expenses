<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EstimateEmail extends Model
{
    protected $fillable = [
        'estimate_id',
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

    public function estimate(): BelongsTo
    {
        return $this->belongsTo(Estimate::class);
    }

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}
