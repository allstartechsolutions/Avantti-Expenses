<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExpenseChangeHistory extends Model
{
    protected $fillable = [
        'expense_id',
        'expense_payment_id',
        'action',
        'changed_by',
        'changes',
    ];

    protected $casts = [
        'changes' => 'array',
    ];

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function expensePayment(): BelongsTo
    {
        return $this->belongsTo(ExpensePayment::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    // =========================================================================
    // DISPLAY HELPERS
    // =========================================================================

    /**
     * Get a human-readable description of the change.
     */
    public function getActionLabel(): string
    {
        $label = match ($this->action) {
            'marked_paid' => __('Marked as paid'),
            'unmarked_paid' => __('Payment reverted'),
            'marked_overdue' => __('Marked as overdue'),
            'marked_pending' => __('Marked as pending'),
            'edited' => __('Edited'),
            default => ucfirst(str_replace('_', ' ', $this->action)),
        };

        if ($this->expense_payment_id && $this->expensePayment) {
            $label .= ' (' . __('Installment') . ' ' . $this->expensePayment->getPaymentLabel() . ')';
        }

        return $label;
    }

    /**
     * Get color for the action badge.
     */
    public function getActionColor(): string
    {
        return match ($this->action) {
            'marked_paid' => 'green',
            'unmarked_paid' => 'yellow',
            'marked_overdue' => 'red',
            'marked_pending' => 'blue',
            'edited' => 'gray',
            default => 'gray',
        };
    }
}
