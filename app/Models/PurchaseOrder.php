<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PurchaseOrder extends Model
{
    protected $fillable = [
        'project_id',
        'job_site_id',
        'supplier_id',
        'quotation_id',
        'expense_id',
        'status',
        'revision_number',
        'po_number',
        'po_date',
        'notes',
        'receipt_path',
        'payment_method',
        'is_auto_payment',
        'total_installments',
        'payment_frequency',
        'payment_due_date',
        'total_amount',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'po_date' => 'date',
        'is_auto_payment' => 'boolean',
        'payment_due_date' => 'date',
        'approved_at' => 'datetime',
        'revision_number' => 'integer',
        'total_installments' => 'integer',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($purchaseOrder) {
            if ($purchaseOrder->receipt_path && Storage::exists($purchaseOrder->receipt_path)) {
                Storage::delete($purchaseOrder->receipt_path);
            }
        });
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function jobSite(): BelongsTo
    {
        return $this->belongsTo(JobSite::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    /** The quotation round this order was awarded from, when it came from one. */
    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class)->orderBy('sort_order');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(PurchaseOrderStatusHistory::class)->orderBy('created_at', 'desc');
    }

    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable')->latest();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // =========================================================================
    // ACCESSORS (Cents ↔ Dollars)
    // =========================================================================

    protected function totalAmount(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => round($value / 100, 2),
            set: fn ($value) => round($value * 100),
        );
    }

    // =========================================================================
    // STATUS CHECKS
    // =========================================================================

    public function isProjectLevel(): bool
    {
        return is_null($this->job_site_id);
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function canBeEdited(): bool
    {
        // Draft and rejected POs can be edited
        return $this->isDraft() || $this->isRejected();
    }

    public function canBeSubmitted(): bool
    {
        // Draft POs can be submitted, rejected POs use reviseAndResubmit instead
        return $this->isDraft() && $this->items()->exists();
    }

    public function canBeApproved(): bool
    {
        return $this->isPending();
    }

    public function canBeRejected(): bool
    {
        // Pending POs can always be rejected
        if ($this->isPending()) {
            return true;
        }

        // Approved POs can be rejected only if expense has no payments
        if ($this->isApproved()) {
            return $this->canChangeStatusFromApproved();
        }

        return false;
    }

    /**
     * Check if status can be changed from approved (expense must have no payments).
     */
    public function canChangeStatusFromApproved(): bool
    {
        if (!$this->isApproved()) {
            return true;
        }

        if (!$this->expense_id || !$this->expense) {
            return true;
        }

        $expense = $this->expense;

        // Cannot change if expense is paid
        if ($expense->status === 'paid') {
            return false;
        }

        // Cannot change if expense has any paid payments
        if ($expense->getPaidInstallmentsCount() > 0) {
            return false;
        }

        return true;
    }

    public function canReviseAndResubmit(): bool
    {
        return $this->isRejected();
    }

    // =========================================================================
    // STATUS ACTIONS
    // =========================================================================

    /**
     * Submit the PO for approval (draft → pending).
     */
    public function submitForApproval(User $user): bool
    {
        if (!$this->canBeSubmitted()) {
            return false;
        }

        $oldStatus = $this->status;

        $this->status = 'pending';
        $this->save();

        $this->recordStatusChange($user, $oldStatus, 'pending');

        return true;
    }

    /**
     * Approve the PO and create an expense.
     */
    public function approve(User $approver): bool
    {
        if (!$this->canBeApproved()) {
            return false;
        }

        $oldStatus = $this->status;

        DB::transaction(function () use ($approver, $oldStatus) {
            $this->status = 'approved';
            $this->approved_by = $approver->id;
            $this->approved_at = now();
            $this->save();

            // Create linked expense
            $expense = $this->createExpenseFromPO();

            // Link expense to PO
            $this->expense_id = $expense->id;
            $this->save();

            $this->recordStatusChange($approver, $oldStatus, 'approved');
        });

        return true;
    }

    /**
     * Reject the PO with optional reason.
     */
    public function reject(User $user, ?string $reason = null): bool
    {
        if (!$this->canBeRejected()) {
            return false;
        }

        $oldStatus = $this->status;

        // If changing from approved, delete linked expense first
        if ($oldStatus === 'approved' && !$this->canChangeStatusFromApproved()) {
            return false;
        }

        if ($oldStatus === 'approved') {
            $this->deleteLinkedExpense();
        }

        $this->status = 'rejected';
        $this->save();

        $this->recordStatusChange($user, $oldStatus, 'rejected', $reason);

        return true;
    }

    /**
     * Cancel the PO.
     */
    public function cancel(User $user, ?string $reason = null): bool
    {
        if ($this->isCancelled()) {
            return false;
        }

        $oldStatus = $this->status;

        // If changing from approved, check if allowed
        if ($oldStatus === 'approved' && !$this->canChangeStatusFromApproved()) {
            return false;
        }

        if ($oldStatus === 'approved') {
            $this->deleteLinkedExpense();
        }

        $this->status = 'cancelled';
        $this->save();

        $this->recordStatusChange($user, $oldStatus, 'cancelled', $reason);

        return true;
    }

    /**
     * Revise a rejected PO and resubmit (rejected → pending).
     */
    public function reviseAndResubmit(User $user): bool
    {
        if (!$this->canReviseAndResubmit()) {
            return false;
        }

        $oldStatus = $this->status;

        // Store snapshot of current data before revision
        $revisionData = $this->toArray();
        $revisionData['items'] = $this->items->toArray();

        $this->status = 'pending';
        $this->revision_number = $this->revision_number + 1;
        $this->save();

        $this->recordStatusChange($user, $oldStatus, 'pending', 'Revised and resubmitted', $revisionData);

        return true;
    }

    // =========================================================================
    // EXPENSE INTEGRATION
    // =========================================================================

    /**
     * Create an expense from this PO.
     */
    public function createExpenseFromPO(): Expense
    {
        // Create the expense
        $expense = Expense::create([
            'purchase_order_id' => $this->id,
            'project_id' => $this->project_id,
            'job_site_id' => $this->job_site_id,
            'supplier_id' => $this->supplier_id,
            'item_name' => $this->po_number ? "PO #{$this->po_number}" : "Purchase Order #{$this->id}",
            'item_type' => null,
            'quantity' => 1,
            'unit_price' => $this->total_amount,
            'total_amount' => $this->total_amount,
            'notes' => $this->notes,
            'expense_date' => $this->po_date,
            'created_by' => $this->created_by,
            'status' => 'unpaid',
            'payment_method' => $this->payment_method,
            'is_auto_payment' => $this->is_auto_payment,
            'total_installments' => $this->total_installments,
            'payment_frequency' => $this->payment_frequency,
            'payment_due_date' => $this->payment_due_date,
        ]);

        // Copy items to expense items
        foreach ($this->items as $poItem) {
            $expense->items()->create([
                'budget_item_id' => $poItem->budget_item_id,
                'catalog_item_id' => $poItem->catalog_item_id,
                'item_name' => $poItem->item_name,
                'item_type' => $poItem->item_type,
                'description' => $poItem->description,
                'quantity' => $poItem->quantity,
                'unit' => $poItem->unit,
                'unit_price' => $poItem->unit_price,
                'total_amount' => $poItem->total_amount,
                'sort_order' => $poItem->sort_order,
            ]);
        }

        // Generate payment schedule if installments
        if ($expense->total_installments > 1) {
            $expense->generatePaymentSchedule();
        }

        return $expense;
    }

    /**
     * Delete the linked expense when status changes from approved.
     */
    public function deleteLinkedExpense(): void
    {
        if ($this->expense_id && $this->expense) {
            $expense = $this->expense;

            // Clear the link first
            $this->expense_id = null;
            $this->approved_by = null;
            $this->approved_at = null;
            $this->save();

            // Delete the expense (will cascade delete items and payments)
            $expense->delete();
        }
    }

    // =========================================================================
    // STATUS HISTORY
    // =========================================================================

    /**
     * Record a status change in the history.
     */
    public function recordStatusChange(
        User $user,
        ?string $oldStatus,
        string $newStatus,
        ?string $reason = null,
        ?array $revisionData = null
    ): PurchaseOrderStatusHistory {
        return $this->statusHistories()->create([
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'changed_by' => $user->id,
            'reason' => $reason,
            'revision_data' => $revisionData,
        ]);
    }

    // =========================================================================
    // CALCULATIONS
    // =========================================================================

    /**
     * Recalculate total_amount from items.
     */
    public function recalculateTotal(): void
    {
        $totalCents = $this->items()->sum('total_amount');
        $this->total_amount = $totalCents / 100;
        $this->save();
    }

    // =========================================================================
    // DISPLAY HELPERS
    // =========================================================================

    /**
     * Get status badge color.
     */
    public function getStatusColor(): string
    {
        return match ($this->status) {
            'draft' => 'gray',
            'pending' => 'yellow',
            'approved' => 'green',
            'rejected' => 'red',
            'cancelled' => 'gray',
            default => 'gray',
        };
    }

    /**
     * Get status label.
     */
    public function getStatusLabel(): string
    {
        return match ($this->status) {
            'draft' => __('Draft'),
            'pending' => __('Pending Approval'),
            'approved' => __('Approved'),
            'rejected' => __('Rejected'),
            'cancelled' => __('Cancelled'),
            default => ucfirst($this->status),
        };
    }

    /**
     * Get the location display string.
     */
    public function getLocationDisplay(): string
    {
        if ($this->isProjectLevel()) {
            return __('Project Level');
        }

        return $this->jobSite?->name ?? __('Unknown');
    }
}
