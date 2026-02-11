<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoicePayment extends Model
{
    protected $fillable = [
        'invoice_id',
        'payment_number',
        'amount',
        'payment_method',
        'payment_date',
        'status',
        'reference_number',
        'notes',
        'gateway',
        'gateway_transaction_id',
        'gateway_auth_code',
        'gateway_status',
        'card_last_four',
        'card_brand',
        'refund_amount',
        'refunded_at',
        'refund_transaction_id',
        'created_by',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'refunded_at' => 'datetime',
    ];

    // Money accessors (cents <-> dollars)

    protected function amount(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => round($value / 100, 2),
            set: fn ($value) => round($value * 100),
        );
    }

    protected function refundAmount(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? round($value / 100, 2) : null,
            set: fn ($value) => $value ? round($value * 100) : null,
        );
    }

    // Relationships

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Status helpers

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isRefunded(): bool
    {
        return $this->status === 'refunded';
    }

    public function isVoided(): bool
    {
        return $this->status === 'voided';
    }

    // Actions

    public function markAsCompleted(): void
    {
        $this->update(['status' => 'completed']);
        $this->invoice->updateStatusFromPayments();
    }

    public function markAsVoided(): void
    {
        $this->update(['status' => 'voided']);
        $this->invoice->updateStatusFromPayments();
    }

    // Display helpers

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

    public function getCardDisplayName(): string
    {
        if (!$this->card_last_four) {
            return $this->getPaymentMethodLabel();
        }

        $brand = $this->card_brand ? ucfirst($this->card_brand) : 'Card';

        return "{$brand} ending in {$this->card_last_four}";
    }

    public function getStatusLabel(): string
    {
        return match ($this->status) {
            'pending' => 'Pending',
            'completed' => 'Completed',
            'failed' => 'Failed',
            'refunded' => 'Refunded',
            'voided' => 'Voided',
            default => ucfirst($this->status),
        };
    }

    public function getStatusColor(): string
    {
        return match ($this->status) {
            'pending' => 'bg-yellow-100 text-yellow-800',
            'completed' => 'bg-green-100 text-green-800',
            'failed' => 'bg-red-100 text-red-800',
            'refunded' => 'bg-purple-100 text-purple-800',
            'voided' => 'bg-gray-100 text-gray-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }
}
