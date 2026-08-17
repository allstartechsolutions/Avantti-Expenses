<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractMeasurementItem extends Model
{
    protected $fillable = [
        'contract_measurement_id',
        'budget_item_id',
        'scheduled_amount',
        'previous_percent',
        'current_percent',
        'period_amount',
    ];

    protected $casts = [
        'previous_percent' => 'decimal:2',
        'current_percent' => 'decimal:2',
    ];

    protected function scheduledAmount(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => round($value / 100, 2),
            set: fn ($value) => round($value * 100),
        );
    }

    protected function periodAmount(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => round($value / 100, 2),
            set: fn ($value) => round($value * 100),
        );
    }

    public function measurement(): BelongsTo
    {
        return $this->belongsTo(ContractMeasurement::class, 'contract_measurement_id');
    }

    public function budgetItem(): BelongsTo
    {
        return $this->belongsTo(BudgetItem::class);
    }

    public function getCostCodeDisplayAttribute(): string
    {
        if ($this->budgetItem) {
            return $this->budgetItem->code.' - '.$this->budgetItem->name;
        }

        return __('Unassigned');
    }
}
