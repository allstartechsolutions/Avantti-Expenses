<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class TaxRate extends Model
{
    protected $fillable = [
        'state',
        'rate',
        'is_default',
        'created_by',
    ];

    protected $casts = [
        'rate' => 'decimal:4',
        'is_default' => 'boolean',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(TaxRateHistory::class);
    }

    public function getFormattedRateAttribute(): string
    {
        return number_format($this->rate * 100, 2) . '%';
    }

    public static function logHistory(?int $taxRateId, string $action, ?string $field = null, ?string $oldValue = null, ?string $newValue = null): void
    {
        TaxRateHistory::create([
            'tax_rate_id' => $taxRateId,
            'action' => $action,
            'field_changed' => $field,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'changed_by' => Auth::id(),
        ]);
    }
}
