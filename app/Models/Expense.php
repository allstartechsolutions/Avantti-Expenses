<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class Expense extends Model
{
    protected $fillable = [
        'project_id',
        'job_site_id',
        'supplier_id',
        'catalog_item_id',
        'purchase_order_id',
        'item_name',
        'item_type',
        'purchase_unit',
        'usage_unit',
        'unit_type_used',
        'quantity',
        'unit_price',
        'total_amount',
        'notes',
        'receipt_path',
        'expense_date',
        'created_by',
        // Payment fields
        'status',
        'payment_method',
        'is_auto_payment',
        'total_installments',
        'payment_frequency',
        'payment_due_date',
        'paid_date',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'expense_date' => 'date',
        'is_auto_payment' => 'boolean',
        'payment_due_date' => 'date',
        'paid_date' => 'date',
    ];

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($expense) {
            if ($expense->receipt_path && Storage::exists($expense->receipt_path)) {
                Storage::delete($expense->receipt_path);
            }
        });
    }

    /**
     * Get/Set unit_price as dollars (stored as cents)
     */
    protected function unitPrice(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => round($value / 100, 2),
            set: fn ($value) => round($value * 100),
        );
    }

    /**
     * Get/Set total_amount as dollars (stored as cents)
     */
    protected function totalAmount(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => round($value / 100, 2),
            set: fn ($value) => round($value * 100),
        );
    }

    /**
     * Get the project this expense belongs to
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the job site this expense belongs to (nullable for project-level expenses)
     */
    public function jobSite(): BelongsTo
    {
        return $this->belongsTo(JobSite::class);
    }

    /**
     * Get the supplier for this expense (optional)
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Get the expense items (line items)
     */
    public function items(): HasMany
    {
        return $this->hasMany(ExpenseItem::class)->orderBy('sort_order');
    }

    /**
     * Check if this is a project-level expense (not tied to a specific job site)
     */
    public function isProjectLevel(): bool
    {
        return is_null($this->job_site_id);
    }

    /**
     * Get the catalog item (if from catalog)
     */
    public function catalogItem(): BelongsTo
    {
        return $this->belongsTo(CatalogItem::class);
    }

    /**
     * Get the user who created this expense
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the purchase order this expense was created from (if any)
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /**
     * Check if this expense was created from a purchase order
     */
    public function isFromPurchaseOrder(): bool
    {
        return !is_null($this->purchase_order_id);
    }

    /**
     * Check if this is a custom (non-catalog) expense
     */
    public function isCustom(): bool
    {
        return is_null($this->catalog_item_id);
    }

    /**
     * Check if this expense uses the new multi-item structure
     */
    public function hasItems(): bool
    {
        return $this->items()->exists();
    }

    /**
     * Recalculate total_amount from expense items
     */
    public function recalculateTotal(): void
    {
        if (!$this->hasItems()) {
            return;
        }

        $totalCents = $this->items()->sum('total_amount');
        $this->total_amount = $totalCents / 100;
        $this->save();
    }

    /**
     * Get the unit name that was actually used for this expense
     */
    public function getDisplayUnit(): string
    {
        if ($this->unit_type_used === 'purchase') {
            return $this->purchase_unit ?? '';
        } elseif ($this->unit_type_used === 'usage') {
            return $this->usage_unit ?? '';
        }
        return $this->usage_unit ?? ''; // fallback for custom items
    }

    // =========================================================================
    // PAYMENT RELATIONSHIPS & METHODS
    // =========================================================================

    /**
     * Get the payment records for this expense (for installments)
     */
    public function payments(): HasMany
    {
        return $this->hasMany(ExpensePayment::class)->orderBy('payment_number');
    }

    /**
     * Check if this is an installment expense (more than one payment)
     */
    public function isInstallment(): bool
    {
        return $this->total_installments > 1;
    }

    /**
     * Check if this is a one-time payment expense
     */
    public function isOneTime(): bool
    {
        return $this->total_installments == 1;
    }

    /**
     * Check if expense can be edited (amount, installments, etc.)
     * Only unpaid expenses with no payments made can be edited
     */
    public function isEditable(): bool
    {
        // Cancelled expenses cannot be edited
        if ($this->status === 'cancelled') {
            return false;
        }

        // One-time expenses: editable if unpaid
        if ($this->isOneTime()) {
            return $this->status === 'unpaid';
        }

        // Installment expenses: editable if no payments have been made
        return $this->getPaidInstallmentsCount() === 0;
    }

    /**
     * Get total amount paid (for installments)
     */
    public function getPaidAmount(): float
    {
        if ($this->isOneTime()) {
            return $this->status === 'paid' ? $this->total_amount : 0;
        }

        return $this->payments()->where('status', 'paid')->sum('amount') / 100;
    }

    /**
     * Get total amount pending
     */
    public function getPendingAmount(): float
    {
        return $this->total_amount - $this->getPaidAmount();
    }

    /**
     * Get number of paid installments
     */
    public function getPaidInstallmentsCount(): int
    {
        return $this->payments()->where('status', 'paid')->count();
    }

    /**
     * Get number of pending installments
     */
    public function getPendingInstallmentsCount(): int
    {
        return $this->payments()->where('status', '!=', 'paid')->count();
    }

    /**
     * Get payment progress as percentage
     */
    public function getPaymentProgress(): int
    {
        if ($this->isOneTime()) {
            return $this->status === 'paid' ? 100 : 0;
        }

        if ($this->total_installments == 0) {
            return 0;
        }

        return (int) round(($this->getPaidInstallmentsCount() / $this->total_installments) * 100);
    }

    /**
     * Get display label for payments (e.g., "1x" or "3/10")
     */
    public function getPaymentLabel(): string
    {
        if ($this->isOneTime()) {
            return '1x';
        }

        return $this->getPaidInstallmentsCount() . '/' . $this->total_installments;
    }

    /**
     * Update expense status based on payment records
     * Called when a payment status changes
     */
    public function updateStatusFromPayments(): void
    {
        // Don't update if cancelled
        if ($this->status === 'cancelled') {
            return;
        }

        // One-time expenses don't use this method
        if ($this->isOneTime()) {
            return;
        }

        $totalPayments = $this->payments()->count();
        $paidPayments = $this->payments()->where('status', 'paid')->count();
        $overduePayments = $this->payments()->where('status', 'overdue')->count();

        if ($overduePayments > 0) {
            $this->status = 'overdue';
        } elseif ($paidPayments === 0) {
            $this->status = 'unpaid';
        } elseif ($paidPayments === $totalPayments) {
            $this->status = 'paid';
        } else {
            $this->status = 'partial';
        }

        $this->save();
    }

    /**
     * Generate payment schedule for installment expense
     *
     * @param array|null $customAmounts Array of amounts for each payment (optional)
     */
    public function generatePaymentSchedule(?array $customAmounts = null): void
    {
        // Only for installment expenses
        if ($this->isOneTime()) {
            return;
        }

        // Delete existing payments (only if none are paid)
        if ($this->getPaidInstallmentsCount() > 0) {
            return;
        }

        $this->payments()->delete();

        $frequency = $this->payment_frequency;
        $startDate = $this->payment_due_date ?? $this->expense_date;

        // Calculate equal amounts if not custom
        if ($customAmounts === null) {
            $equalAmount = round($this->total_amount / $this->total_installments, 2);
            $customAmounts = array_fill(0, $this->total_installments, $equalAmount);

            // Adjust last payment for rounding differences
            $total = array_sum($customAmounts);
            $diff = $this->total_amount - $total;
            if ($diff != 0) {
                $customAmounts[$this->total_installments - 1] += $diff;
            }
        }

        // Create payment records
        for ($i = 0; $i < $this->total_installments; $i++) {
            $dueDate = $this->calculateDueDate($startDate, $frequency, $i);

            $this->payments()->create([
                'payment_number' => $i + 1,
                'amount' => $customAmounts[$i],
                'due_date' => $dueDate,
                'status' => 'pending',
            ]);
        }
    }

    /**
     * Calculate due date for a specific payment number
     */
    protected function calculateDueDate(Carbon $startDate, string $frequency, int $paymentIndex): Carbon
    {
        $date = $startDate->copy();

        return match ($frequency) {
            'weekly' => $date->addWeeks($paymentIndex),
            'biweekly' => $date->addWeeks($paymentIndex * 2),
            'monthly' => $date->addMonths($paymentIndex),
            default => $date->addMonths($paymentIndex),
        };
    }

    /**
     * Mark one-time expense as paid
     */
    public function markAsPaid(?string $paymentMethod = null, ?Carbon $paidDate = null): void
    {
        if (!$this->isOneTime()) {
            return;
        }

        $this->status = 'paid';
        $this->paid_date = $paidDate ?? now();

        if ($paymentMethod) {
            $this->payment_method = $paymentMethod;
        }

        $this->save();
    }

    /**
     * Mark expense as cancelled
     */
    public function markAsCancelled(): void
    {
        $this->status = 'cancelled';
        $this->save();
    }

    /**
     * Check if expense has any overdue payments
     */
    public function hasOverduePayments(): bool
    {
        if ($this->isOneTime()) {
            return $this->status === 'overdue';
        }

        return $this->payments()->where('status', 'overdue')->exists();
    }

    /**
     * Get the next pending payment (for installments)
     */
    public function getNextPendingPayment(): ?ExpensePayment
    {
        return $this->payments()
            ->where('status', 'pending')
            ->orderBy('due_date')
            ->first();
    }

    /**
     * Get all pending payments ordered by due date
     */
    public function getPendingPayments()
    {
        return $this->payments()
            ->where('status', '!=', 'paid')
            ->orderBy('due_date')
            ->get();
    }
}
