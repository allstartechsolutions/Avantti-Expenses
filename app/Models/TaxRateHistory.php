<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxRateHistory extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'tax_rate_id',
        'action',
        'field_changed',
        'old_value',
        'new_value',
        'changed_by',
    ];

    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
