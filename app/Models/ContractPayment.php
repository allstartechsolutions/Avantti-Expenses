<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContractPayment extends Model
{
    protected $fillable = [
        'contract_id',
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
    ];

    protected function amount(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => round($value / 100, 2),
            set: fn ($value) => round($value * 100),
        );
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ContractPaymentItem::class);
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
        return match ($this->payment_method) {
            'cash' => 'Cash',
            'check' => 'Check',
            'credit_card' => 'Credit Card',
            'debit_card' => 'Debit Card',
            'bank_transfer' => 'Bank Transfer',
            'pix' => 'PIX',
            'other' => 'Other',
            default => ucfirst($this->payment_method),
        };
    }
}
