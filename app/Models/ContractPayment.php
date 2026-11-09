<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContractPayment extends Model
{
    /** What the contract payment and batch screens pre-select for a new payment. */
    public const DEFAULT_METHOD = 'check';

    protected $fillable = [
        'contract_id',
        'contract_schedule_item_id',
        'contract_measurement_id',
        'payment_batch_item_id',
        'is_retention_release',
        'amount',
        'payment_date',
        'payment_method',
        'phase',
        'reference_number',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'is_retention_release' => 'boolean',
    ];

    protected function amount(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => round($value / 100, 2),
            set: fn ($value) => round($value * 100),
        );
    }

    public function backChargeDeductions(): HasMany
    {
        return $this->hasMany(BackChargeDeduction::class);
    }

    /** Back charges deducted from this payment (docs/back-charges.md). */
    public function getBackChargesDeducted(): float
    {
        $cents = $this->relationLoaded('backChargeDeductions')
            ? $this->backChargeDeductions->sum(fn ($d) => $d->getRawOriginal('amount'))
            : $this->backChargeDeductions()->sum('amount');

        return round($cents / 100, 2);
    }

    /**
     * Cash out, in cents, for a query of payments: what was paid less the
     * back charges deducted from those payments. Cash reports sum with this,
     * never with ->sum('amount') (docs/back-charges.md).
     */
    public static function cashCents($query): int
    {
        $gross = (int) (clone $query)->sum('contract_payments.amount');
        $deducted = (int) BackChargeDeduction::query()
            ->whereIn('contract_payment_id', (clone $query)->select('contract_payments.id'))
            ->sum('amount');

        return $gross - $deducted;
    }

    /** The check: the payment less the back charges deducted from it. */
    public function getNetAmount(): float
    {
        return round($this->amount - $this->getBackChargesDeducted(), 2);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ContractPaymentItem::class);
    }

    public function scheduleItem(): BelongsTo
    {
        return $this->belongsTo(ContractScheduleItem::class, 'contract_schedule_item_id');
    }

    /** The payment batch line this payment was approved from, if any. */
    public function batchItem(): BelongsTo
    {
        return $this->belongsTo(PaymentBatchItem::class, 'payment_batch_item_id');
    }

    public function measurement(): BelongsTo
    {
        return $this->belongsTo(ContractMeasurement::class, 'contract_measurement_id');
    }

    /**
     * Total of the payment's cost-code line items. A payment without
     * items (all pre-existing payments) counts entirely as unallocated.
     */
    public function getItemizedTotal(): float
    {
        return round($this->items->sum(fn ($item) => $item->getRawOriginal('amount')) / 100, 2);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getPaymentMethodLabel(): string
    {
        // Translated here so every consumer gets it — some call sites
        // wrap this in __() and some do not; a second __() on an already
        // translated string is a no-op.
        return match ($this->payment_method) {
            'cash' => __('Cash'),
            'check' => __('Check'),
            'credit_card' => __('Credit Card'),
            'debit_card' => __('Debit Card'),
            'bank_transfer' => __('Bank Transfer'),
            'pix' => __('PIX'),
            'other' => __('Other'),
            default => ucfirst($this->payment_method),
        };
    }
}
