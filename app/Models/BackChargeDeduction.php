<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One part of a back charge taken from one payment (docs/back-charges.md).
 * It lowers its contract once it has a contract_payment_id: a direct payment
 * sets it on save, a batch line when it is approved.
 */
class BackChargeDeduction extends Model
{
    protected $fillable = [
        'back_charge_id',
        'contract_id',
        'payment_batch_item_id',
        'contract_payment_id',
        'amount',
        'created_by',
    ];

    protected function amount(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => round($value / 100, 2),
            set: fn ($value) => round($value * 100),
        );
    }

    public function backCharge(): BelongsTo
    {
        return $this->belongsTo(BackCharge::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function batchItem(): BelongsTo
    {
        return $this->belongsTo(PaymentBatchItem::class, 'payment_batch_item_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(ContractPayment::class, 'contract_payment_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isTaken(): bool
    {
        return $this->contract_payment_id !== null;
    }
}
