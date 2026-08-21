<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Purchase requisition (solicitação de compra) — the ask from the site that
 * starts the buy-side chain: requisition → quotation → award → PO / contract.
 */
class PurchaseRequisition extends Model
{
    protected $fillable = [
        'project_id',
        'job_site_id',
        'requisition_number',
        'type',
        'title',
        'justification',
        'needed_by',
        'priority',
        'status',
        'budget_item_id',
        'requested_by',
        'requested_by_name',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'created_by',
    ];

    protected $casts = [
        'needed_by' => 'date',
        'reviewed_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        // Attachment rows own their file, so deleting them through Eloquent is
        // what clears the disk too.
        static::deleting(function (PurchaseRequisition $requisition) {
            $requisition->attachments->each->delete();
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

    public function budgetItem(): BelongsTo
    {
        return $this->belongsTo(BudgetItem::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseRequisitionItem::class)->orderBy('sort_order');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(PurchaseRequisitionStatusHistory::class)->orderBy('created_at', 'desc');
    }

    /** A requisition may be quoted by more than one round (split by speciality). */
    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class)->orderBy('created_at', 'desc');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')->latest();
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // =========================================================================
    // NUMBERING
    // =========================================================================

    /**
     * Create a record with the next number, retrying if another request took
     * that number first. The column is unique, so the loser of the race gets a
     * duplicate-key error rather than a duplicate document — this turns that
     * into simply taking the next one.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function createWithNumber(array $attributes): self
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            try {
                return static::create($attributes + ['requisition_number' => static::generateRequisitionNumber()]);
            } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
                if ($attempt === 5) {
                    throw $e;
                }
            }
        }

        throw new \RuntimeException('Could not allocate a number.');
    }

    public static function generateRequisitionNumber(): string
    {
        // Numeric max, not string max: 'REQ-9999' > 'REQ-10000' lexically.
        $number = (int) static::query()
            ->selectRaw('MAX(CAST(SUBSTRING(requisition_number, 5) AS UNSIGNED)) AS max_number')
            ->value('max_number');

        return 'REQ-'.str_pad($number + 1, 4, '0', STR_PAD_LEFT);
    }

    // =========================================================================
    // STATUS
    // =========================================================================

    public function recordStatusChange(User $user, ?string $oldStatus, string $newStatus, ?string $reason = null): void
    {
        $this->statusHistories()->create([
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'changed_by' => $user->id,
            'reason' => $reason,
        ]);
    }

    /**
     * Where the requisition stands is decided by the quotations that point at
     * it: a live round makes it 'quoted', a converted one 'fulfilled', and
     * losing every round puts it back to 'approved'. Only the derived part of
     * the lifecycle is touched — a draft, rejected or cancelled requisition is
     * left exactly as it is.
     */
    public function refreshChainStatus(): void
    {
        if (! in_array($this->status, ['approved', 'quoted', 'fulfilled'], true)) {
            return;
        }

        $quotations = $this->quotations()->get(['status']);
        $live = $quotations->where('status', '!=', 'cancelled');

        // One requisition may be split across several rounds, so it is only
        // fulfilled once every live round has been converted. Treating the
        // first conversion as fulfilment made it unquotable while a second
        // round was still open, and that round then lost its link back.
        $status = match (true) {
            $live->isNotEmpty() && $live->every(fn ($row) => $row->status === 'converted') => 'fulfilled',
            $live->isNotEmpty() => 'quoted',
            default => 'approved',
        };

        if ($status !== $this->status) {
            $this->update(['status' => $status]);
        }
    }

    /**
     * An approved requisition is what procurement is allowed to quote, and it
     * stays quotable once quoted: one requisition may be split across several
     * rounds — the rebar to the steel merchants, the concrete to the plants —
     * which is standard practice. The screens say a round already exists so
     * the second one is a decision, not an accident.
     */
    public function canBeQuoted(): bool
    {
        return in_array($this->status, ['approved', 'quoted'], true);
    }

    /** Rounds still on the table — a cancelled one no longer covers anything. */
    public function liveQuotations()
    {
        return $this->quotations->where('status', '!=', 'cancelled');
    }

    public function isAlreadyQuoted(): bool
    {
        return $this->liveQuotations()->isNotEmpty();
    }

    /**
     * Only a pending requisition can be approved or rejected, and only by a
     * user allowed to review (admin or manager).
     */
    public function canBeReviewed(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * N1 (docs/permissions-notes.md): a **submitted** requisition is locked.
     *
     * It used to stay editable while `pending`, so what was being asked for
     * could change after somebody had been asked to approve it — which makes
     * the approval a signature on a moving document. Now the content is fixed
     * the moment it is submitted; to change it, send it back to draft, which
     * is a visible act and costs the requisition its place in the queue.
     */
    public function canBeEdited(): bool
    {
        return $this->status === 'draft';
    }

    /**
     * Withdraw a submitted requisition so it can be changed. Whoever does it
     * needs `requisitions.edit`, and it is theirs or they hold
     * `requisitions.approve` — the rule lives in the component.
     */
    public function canReturnToDraft(): bool
    {
        return $this->status === 'pending';
    }

    public function canBeDeleted(): bool
    {
        return in_array($this->status, ['draft', 'rejected', 'cancelled'], true);
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, ['draft', 'pending', 'approved'], true);
    }

    public function isProjectLevel(): bool
    {
        return $this->job_site_id === null;
    }

    // =========================================================================
    // DISPLAY HELPERS
    // =========================================================================

    public function getStatusColor(): string
    {
        return match ($this->status) {
            'draft' => 'gray',
            'pending' => 'yellow',
            'approved' => 'green',
            'rejected' => 'red',
            'quoted' => 'blue',
            'fulfilled' => 'green',
            'cancelled' => 'gray',
            default => 'gray',
        };
    }

    public function getStatusLabel(): string
    {
        return match ($this->status) {
            'draft' => __('Draft'),
            'pending' => __('Pending Approval'),
            'approved' => __('Approved'),
            'rejected' => __('Rejected'),
            'quoted' => __('Quoted'),
            'fulfilled' => __('Fulfilled'),
            'cancelled' => __('Cancelled'),
            default => ucfirst($this->status),
        };
    }

    public function getPriorityColor(): string
    {
        return match ($this->priority) {
            'urgent' => 'red',
            'normal' => 'blue',
            'low' => 'gray',
            default => 'gray',
        };
    }

    public function getPriorityLabel(): string
    {
        return match ($this->priority) {
            'urgent' => __('Urgent'),
            'normal' => __('Normal'),
            'low' => __('Low'),
            default => ucfirst($this->priority),
        };
    }

    public function getTypeLabel(): string
    {
        return match ($this->type) {
            'material' => __('Material'),
            'service' => __('Service'),
            default => ucfirst($this->type),
        };
    }

    /**
     * Who asked for it — the free-text name wins, because office staff often
     * raise the requisition on behalf of someone on site with no login.
     */
    public function getRequesterName(): string
    {
        return $this->requested_by_name
            ?: ($this->requestedBy?->name ?? $this->createdBy?->name ?? __('Unknown'));
    }

    public function getLocationDisplay(): string
    {
        if ($this->isProjectLevel()) {
            return __('Project Level');
        }

        return $this->jobSite?->job_site_name ?? __('Unknown');
    }

    /**
     * Past its needed-by date and not yet dealt with.
     */
    public function isOverdue(): bool
    {
        return $this->needed_by
            && $this->needed_by->isPast()
            && in_array($this->status, ['draft', 'pending', 'approved', 'quoted'], true);
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeOpen($query)
    {
        return $query->whereIn('status', ['draft', 'pending', 'approved', 'quoted']);
    }
}
