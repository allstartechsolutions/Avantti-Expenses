<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentBatchItem extends Model
{
    protected $fillable = [
        'payment_batch_id',
        'contract_id',
        'contract_schedule_item_id',
        'contract_measurement_id',
        'is_retention_release',
        'amount',
        'payment_method',
        'phase',
        'notes',
        'status',
        'approved_at',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'is_retention_release' => 'boolean',
    ];

    protected function amount(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => round($value / 100, 2),
            set: fn ($value) => round($value * 100),
        );
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(PaymentBatch::class, 'payment_batch_id');
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function scheduleItem(): BelongsTo
    {
        return $this->belongsTo(ContractScheduleItem::class, 'contract_schedule_item_id');
    }

    public function measurement(): BelongsTo
    {
        return $this->belongsTo(ContractMeasurement::class, 'contract_measurement_id');
    }

    public function getPaymentMethodLabel(): string
    {
        return match ($this->payment_method) {
            'cash' => 'Cash',
            'check' => 'Check',
            'credit_card' => 'Credit Card',
            'debit_card' => 'Debit Card',
            'bank_transfer' => 'Bank Transfer',
            'pix' => 'PIX',
            'other' => 'Other',
            default => $this->payment_method ? ucfirst($this->payment_method) : '-',
        };
    }

    public function getStatusLabel(): string
    {
        return match ($this->status) {
            'pending' => 'Pending',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            default => ucfirst($this->status),
        };
    }

    public function getStatusColor(): string
    {
        return match ($this->status) {
            'pending' => 'amber',
            'approved' => 'green',
            'rejected' => 'red',
            default => 'gray',
        };
    }
}
