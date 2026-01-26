<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class ExpensePayment extends Model
{
    protected $fillable = [
        'expense_id',
        'payment_number',
        'amount',
        'due_date',
        'paid_date',
        'status',
        'payment_method',
        'notes',
    ];

    protected $casts = [
        'due_date' => 'date',
        'paid_date' => 'date',
    ];

    /**
     * Get/Set amount as dollars (stored as cents)
     */
    protected function amount(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => round($value / 100, 2),
            set: fn ($value) => round($value * 100),
        );
    }

    /**
     * Get the expense this payment belongs to
     */
    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    /**
     * Mark this payment as paid
     */
    public function markAsPaid(?string $paymentMethod = null, ?Carbon $paidDate = null): void
    {
        $this->status = 'paid';
        $this->paid_date = $paidDate ?? now();

        if ($paymentMethod) {
            $this->payment_method = $paymentMethod;
        }

        $this->save();

        // Update parent expense status
        $this->expense->updateStatusFromPayments();
    }

    /**
     * Mark this payment as overdue
     */
    public function markAsOverdue(): void
    {
        $this->status = 'overdue';
        $this->save();

        // Update parent expense status to overdue
        $this->expense->update(['status' => 'overdue']);
    }

    /**
     * Mark this payment as pending (revert from overdue)
     */
    public function markAsPending(): void
    {
        $this->status = 'pending';
        $this->save();

        // Recalculate parent expense status
        $this->expense->updateStatusFromPayments();
    }

    /**
     * Check if payment is pending
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if payment is paid
     */
    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    /**
     * Check if payment is overdue
     */
    public function isOverdue(): bool
    {
        return $this->status === 'overdue';
    }

    /**
     * Check if payment is past due date but not yet marked overdue
     */
    public function isPastDue(): bool
    {
        return $this->status === 'pending' && $this->due_date->isPast();
    }

    /**
     * Get the effective payment method (own or inherited from expense)
     */
    public function getEffectivePaymentMethod(): ?string
    {
        return $this->payment_method ?? $this->expense->payment_method;
    }

    /**
     * Get formatted payment label (e.g., "1/10", "3/6")
     */
    public function getPaymentLabel(): string
    {
        return $this->payment_number . '/' . $this->expense->total_installments;
    }
}
