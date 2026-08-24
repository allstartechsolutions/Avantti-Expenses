<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;
use App\Models\Concerns\HasPaymentMethodLabel;

class ExpensePayment extends Model
{
    use HasPaymentMethodLabel;

    protected $fillable = [
        'expense_id',
        'payment_number',
        'amount',
        'due_date',
        'paid_date',
        'paid_by',
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
     * An installment has no project or job site of its own; permission
     * questions about it are questions about its expense.
     */
    public function permissionScope(): ?Expense
    {
        return $this->relationLoaded('expense') ? $this->expense : $this->expense()->first();
    }

    /**
     * Get the user who marked this payment as paid
     */
    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    /**
     * Mark this payment as paid
     */
    public function markAsPaid(?string $paymentMethod = null, ?Carbon $paidDate = null): void
    {
        $oldStatus = $this->status;

        $this->status = 'paid';
        $this->paid_date = $paidDate ?? now();
        $this->paid_by = auth()->id();

        if ($paymentMethod) {
            $this->payment_method = $paymentMethod;
        }

        $this->save();

        // Update parent expense status
        $this->expense->updateStatusFromPayments();

        $this->expense->recordChange('marked_paid', [
            'status' => ['old' => $oldStatus, 'new' => 'paid'],
        ], $this->id);
    }

    /**
     * Mark this payment as overdue
     */
    public function markAsOverdue(): void
    {
        $oldStatus = $this->status;

        $this->status = 'overdue';
        $this->save();

        // Update parent expense status to overdue
        $this->expense->update(['status' => 'overdue']);

        $this->expense->recordChange('marked_overdue', [
            'status' => ['old' => $oldStatus, 'new' => 'overdue'],
        ], $this->id);
    }

    /**
     * Mark this payment as pending (revert from overdue or paid)
     */
    public function markAsPending(): void
    {
        $oldStatus = $this->status;

        $this->status = 'pending';

        if ($oldStatus === 'paid') {
            $this->paid_date = null;
            $this->paid_by = null;
        }

        $this->save();

        // Recalculate parent expense status
        $this->expense->updateStatusFromPayments();

        $this->expense->recordChange(
            $oldStatus === 'paid' ? 'unmarked_paid' : 'marked_pending',
            ['status' => ['old' => $oldStatus, 'new' => 'pending']],
            $this->id
        );
    }

    /**
     * Change the due date of this payment (negotiated postponements).
     * An overdue payment moved to today or later goes back to pending.
     */
    public function changeDueDate(Carbon $newDate): void
    {
        $oldDate = $this->due_date;

        if ($oldDate->isSameDay($newDate)) {
            return;
        }

        $this->due_date = $newDate;

        $statusChange = null;
        if ($this->status === 'overdue' && $newDate->gte(today())) {
            $this->status = 'pending';
            $statusChange = ['old' => 'overdue', 'new' => 'pending'];
        }

        $this->save();

        if ($statusChange) {
            $this->expense->updateStatusFromPayments();
        }

        $changes = ['due_date' => ['old' => $oldDate->format('Y-m-d'), 'new' => $newDate->format('Y-m-d')]];
        if ($statusChange) {
            $changes['status'] = $statusChange;
        }

        $this->expense->recordChange('due_date_changed', $changes, $this->id);
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
     * Human label for the instalment's status.
     *
     * A *pagamento* is masculine, so the shared keys are the right gender and
     * no new ones are needed — unlike Expense, see Expense::getStatusLabel().
     */
    public static function statusLabel(?string $status): string
    {
        return match ($status) {
            'pending' => __('Pending'),
            'paid' => __('Paid'),
            'overdue' => __('Overdue'),
            default => ucfirst(str_replace('_', ' ', (string) $status)),
        };
    }

    public function getStatusLabel(): string
    {
        return static::statusLabel($this->status);
    }

    /** Label for the method actually used, falling back to the expense's. */
    public function getEffectivePaymentMethodLabel(): ?string
    {
        return static::paymentMethodLabel($this->getEffectivePaymentMethod());
    }

    /**
     * Get formatted payment label (e.g., "1/10", "3/6")
     */
    public function getPaymentLabel(): string
    {
        return $this->payment_number . '/' . $this->expense->total_installments;
    }
}
