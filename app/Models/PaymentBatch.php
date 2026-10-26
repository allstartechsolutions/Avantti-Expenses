<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentBatch extends Model
{
    protected $fillable = [
        'name',
        'status',
        'payment_date',
        'notes',
        'client_id',
        'project_id',
        'subcontractor_id',
        'project_manager_id',
        'supervisor_id',
        'contract_status_filter',
        'show_zero_balance',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'payment_date' => 'date',
        'approved_at' => 'datetime',
        'show_zero_balance' => 'boolean',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(PaymentBatchItem::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function subcontractor(): BelongsTo
    {
        return $this->belongsTo(Subcontractor::class);
    }

    public function projectManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'project_manager_id');
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    /**
     * Derive the status from the lines still in the batch — after one is
     * approved or rejected, or taken out with its contract. An empty or
     * cancelled batch keeps the status it had.
     *
     * $approverId is the person approving lines right now. A batch completed
     * because its last pending line left with a deleted contract was approved
     * by nobody in that act: it records no approver, and the time of its last
     * approved line.
     */
    public function refreshStatusFromItems(?int $approverId = null): void
    {
        $items = $this->items()->get(['status', 'approved_at']);

        if ($items->isEmpty()) {
            return;
        }

        $pendingCount = $items->where('status', 'pending')->count();
        $approvedCount = $items->where('status', 'approved')->count();

        if ($pendingCount === 0 && $approvedCount > 0) {
            // Only the step that completes the batch approves it; a batch
            // already approved keeps who approved it and when.
            if ($this->status !== 'approved') {
                $this->update([
                    'status' => 'approved',
                    'approved_by' => $approverId,
                    'approved_at' => $approverId ? now() : $items->max('approved_at'),
                ]);
            }
        } elseif ($approvedCount > 0) {
            $this->update(['status' => 'partially_approved']);
        } elseif (in_array($this->status, ['partially_approved', 'approved'], true)) {
            // Its approved lines left with their contracts: nothing in it is
            // approved any more.
            $this->update([
                'status' => 'draft',
                'approved_by' => null,
                'approved_at' => null,
            ]);
        }
    }

    public function canBeEdited(): bool
    {
        return in_array($this->status, ['draft', 'partially_approved']);
    }

    public function getTotalAmount(): float
    {
        return round($this->items()->sum('amount') / 100, 2);
    }

    public function getStatusLabel(): string
    {
        return match ($this->status) {
            'draft' => 'Draft',
            'partially_approved' => 'Partially Approved',
            'approved' => 'Approved',
            'cancelled' => 'Cancelled',
            default => ucfirst($this->status),
        };
    }

    public function getStatusColor(): string
    {
        return match ($this->status) {
            'draft' => 'gray',
            'partially_approved' => 'amber',
            'approved' => 'green',
            'cancelled' => 'red',
            default => 'gray',
        };
    }
}
