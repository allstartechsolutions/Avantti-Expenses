<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * A quotation round (cotação): one scope, several vendors asked to price it.
 * Phase 2 of the buy-side chain — see docs/quotation-module-plan.md.
 */
class Quotation extends Model
{
    protected $fillable = [
        'project_id',
        'job_site_id',
        'purchase_requisition_id',
        'quotation_number',
        'type',
        'title',
        'description',
        'needed_by',
        'responses_due_at',
        'status',
        'budget_item_id',
        'awarded_vendor_id',
        'awarded_at',
        'awarded_by',
        'award_reason',
        'is_split_award',
        'converted_type',
        'converted_id',
        'created_by',
        'assigned_to',
        'assigned_at',
        'due_notified_at',
        'overdue_notified_at',
    ];

    protected $casts = [
        'needed_by' => 'date',
        'responses_due_at' => 'date',
        'awarded_at' => 'datetime',
        'is_split_award' => 'boolean',
        'assigned_at' => 'datetime',
        'due_notified_at' => 'datetime',
        'overdue_notified_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        // Pushing the response deadline re-arms both warnings. Done here
        // rather than at the call sites because there are several of them —
        // the form, the send screen — and a stamp left behind by any one of
        // them would silently disarm the reminder for good.
        static::saving(function (Quotation $quotation) {
            if ($quotation->dueWarningsShouldReArm()) {
                $quotation->due_notified_at = null;
                $quotation->overdue_notified_at = null;
            }
        });

        static::deleting(function (Quotation $quotation) {
            $quotation->attachments->each->delete();

            // The vendor rows go with the round through a database cascade,
            // which never fires their model events — so their proposals'
            // attachments are cleared here, before the cascade runs.
            $quotation->quotationVendors->each(fn ($row) => $row->attachments->each->delete());
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

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequisition::class, 'purchase_requisition_id');
    }

    public function budgetItem(): BelongsTo
    {
        return $this->belongsTo(BudgetItem::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class)->orderBy('sort_order');
    }

    public function quotationVendors(): HasMany
    {
        return $this->hasMany(QuotationVendor::class);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(QuotationStatusHistory::class)->orderBy('created_at', 'desc');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')->latest();
    }

    public function awardedVendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'awarded_vendor_id');
    }

    public function awardedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'awarded_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** The one person answerable for getting the prices in. */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Everyone else working the round. The owner is an implicit collaborator
     * and is deliberately not duplicated here.
     */
    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'quotation_assignees')
            ->withPivot(['assigned_by', 'assigned_at'])
            ->withTimestamps();
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
                return static::create($attributes + ['quotation_number' => static::generateQuotationNumber()]);
            } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
                if ($attempt === 5) {
                    throw $e;
                }
            }
        }

        throw new \RuntimeException('Could not allocate a number.');
    }

    public static function generateQuotationNumber(): string
    {
        // Numeric max, not string max: 'COT-9999' > 'COT-10000' lexically.
        $number = (int) static::query()
            ->selectRaw('MAX(CAST(SUBSTRING(quotation_number, 5) AS UNSIGNED)) AS max_number')
            ->value('max_number');

        return 'COT-'.str_pad($number + 1, 4, '0', STR_PAD_LEFT);
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
     * The scope and the invited list are still open until the round has been
     * sent out; after that, changing what was asked would invalidate the
     * proposals already received.
     */
    public function canBeEdited(): bool
    {
        return in_array($this->status, ['draft', 'sent'], true);
    }

    public function canBeSent(): bool
    {
        return $this->status === 'draft'
            && $this->items()->exists()
            && $this->quotationVendors()->exists();
    }

    public function canBeCancelled(): bool
    {
        return ! in_array($this->status, ['converted', 'cancelled'], true);
    }

    public function canBeDeleted(): bool
    {
        return in_array($this->status, ['draft', 'cancelled'], true);
    }

    public function isOpen(): bool
    {
        return ! in_array($this->status, ['converted', 'cancelled'], true);
    }

    public function isProjectLevel(): bool
    {
        return $this->job_site_id === null;
    }

    // =========================================================================
    // PROPOSAL COUNTS — the 2/3 rule the award will enforce (phase 6)
    // =========================================================================

    public function invitedCount(): int
    {
        return $this->quotationVendors->count();
    }

    public function respondedCount(): int
    {
        return $this->quotationVendors
            ->whereIn('status', ['responded', 'awarded', 'rejected'])
            ->count();
    }

    public function declinedCount(): int
    {
        return $this->quotationVendors->where('status', 'declined')->count();
    }

    public function awaitingCount(): int
    {
        return $this->quotationVendors->where('status', 'invited')->count();
    }

    /**
     * Replies that actually priced something.
     *
     * A vendor who answers "cannot supply" on every line has responded, and is
     * counted as a response on screen, but there is nothing in it to compare —
     * so it cannot help satisfy the rule that exists to stop a round being
     * awarded on a single quote.
     */
    public function pricedProposalCount(): int
    {
        return $this->quotationVendors
            ->whereIn('status', ['responded', 'awarded', 'rejected'])
            ->filter(fn ($vendor) => $vendor->hasAnyPrice())
            ->count();
    }

    /** Three proposals is the Brazilian norm; two is the floor. */
    public function meetsProposalMinimum(): bool
    {
        return $this->pricedProposalCount() >= 2;
    }

    public function meetsProposalNorm(): bool
    {
        return $this->pricedProposalCount() >= 3;
    }

    public function responsesOverdue(): bool
    {
        return $this->responses_due_at
            && $this->responses_due_at->isPast()
            && in_array($this->status, ['sent', 'comparing', 'negotiating'], true);
    }

    // =========================================================================
    // THE AWARD
    //
    // Brazilian practice: at least three proposals is the norm and two the
    // floor, and the choice has to carry a written reason so a non-cheapest
    // award can be defended later.
    // =========================================================================

    public function isAwarded(): bool
    {
        return in_array($this->status, ['awarded', 'converted'], true);
    }

    /** Only a live round with enough proposals on the table can be awarded. */
    public function canBeAwarded(): bool
    {
        return in_array($this->status, ['sent', 'comparing', 'negotiating'], true)
            && $this->meetsProposalMinimum();
    }

    /** Awards can be undone until the round has been turned into a PO or contract. */
    public function canRevokeAward(): bool
    {
        return $this->status === 'awarded';
    }

    /** An award becomes what actually gets paid: orders for material, contracts for service. */
    public function canBeConverted(): bool
    {
        return $this->status === 'awarded';
    }

    public function isConverted(): bool
    {
        return $this->status === 'converted';
    }

    /** Everything this round turned into, whichever side it went. */
    public function convertedRecords()
    {
        return $this->type === 'service'
            ? $this->contracts
            : $this->purchaseOrders;
    }

    /** Proposals that may still win: answered, and not withdrawn. */
    public function awardableProposals()
    {
        return $this->quotationVendors->filter(fn ($row) => $row->hasResponded())->values();
    }

    /** On a split award, the vendors who won at least one line. */
    public function splitWinners()
    {
        $winnerIds = $this->items->pluck('awarded_quotation_vendor_id')->filter()->unique();

        return $this->quotationVendors->whereIn('id', $winnerIds)->values();
    }

    /** What the award actually commits, split or whole. */
    public function awardedTotal(): float
    {
        if (! $this->is_split_award) {
            $winner = $this->quotationVendors->firstWhere('vendor_id', $this->awarded_vendor_id);

            return $winner ? $winner->equalizedTotal() : 0.0;
        }

        // A split award commits the winning line prices, plus each winning
        // vendor's own freight, taxes and discount — those are charged once
        // per order, not once per line.
        $lineTotal = $this->items->sum(function ($item) {
            if (! $item->awarded_quotation_vendor_id) {
                return 0;
            }

            $priced = $this->quotationVendors
                ->firstWhere('id', $item->awarded_quotation_vendor_id)
                ?->items->firstWhere('quotation_item_id', $item->id);

            return $priced && ! $priced->is_unavailable ? (float) $priced->total_amount : 0;
        });

        $vendorCharges = $this->splitWinners()->sum(fn ($row) => (float) $row->freight_amount
            + (float) $row->tax_amount
            - (float) $row->discount_amount);

        return round((float) $lineTotal + (float) $vendorCharges, 2);
    }

    /**
     * What an award *would* commit, for a winner set that has not been saved
     * yet — the figure an approval ceiling is checked against before the award
     * happens, since `awardedTotal()` can only read an award already made.
     *
     * The rules are the same: a whole award is the winner's equalized total; a
     * split is the winning line prices plus each winning vendor's own freight,
     * taxes and discount, which are charged once per order rather than once
     * per line.
     *
     * @param  array{vendor_row_ids: array<int>, lines: array<int, int|null>}  $winners
     * @param  \Illuminate\Support\Collection  $awardable  Proposal rows keyed by id.
     */
    public function totalForProposedAward(array $winners, $awardable): float
    {
        $rows = $awardable->only($winners['vendor_row_ids']);

        if (empty($winners['lines'])) {
            $row = $rows->first();

            return $row ? round((float) $row->equalizedTotal(), 2) : 0.0;
        }

        $lineTotal = 0.0;

        foreach ($winners['lines'] as $itemId => $rowId) {
            if (! $rowId) {
                continue;
            }

            $priced = $awardable->get($rowId)?->items->firstWhere('quotation_item_id', $itemId);

            $lineTotal += $priced && ! $priced->is_unavailable ? (float) $priced->total_amount : 0.0;
        }

        $charges = $rows->sum(fn ($row) => (float) $row->freight_amount
            + (float) $row->tax_amount
            - (float) $row->discount_amount);

        return round($lineTotal + (float) $charges, 2);
    }

    /** What converting this round commits, in cents, for an approval ceiling. */
    public function awardedTotalInCents(): int
    {
        return (int) round($this->awardedTotal() * 100);
    }

    /** A service round becomes a contract; anything else becomes a purchase order. */
    public function convertsToContract(): bool
    {
        return $this->type === 'service';
    }

    // =========================================================================
    // DISPLAY HELPERS
    // =========================================================================

    public function getStatusColor(): string
    {
        return match ($this->status) {
            'draft' => 'gray',
            'sent' => 'blue',
            'comparing' => 'blue',
            'negotiating' => 'yellow',
            'awarded' => 'green',
            'converted' => 'green',
            'cancelled' => 'gray',
            default => 'gray',
        };
    }

    public static function statusLabel(?string $status): string
    {
        return match ($status) {
            'draft' => __('Draft'),
            'sent' => __('Sent to Vendors'),
            'comparing' => __('Comparing'),
            'negotiating' => __('Negotiating'),
            'awarded' => __('Awarded'),
            'converted' => __('Converted'),
            'cancelled' => __('Cancelled'),
            default => ucfirst($status),
        };
    }

    public function getStatusLabel(): string
    {
        return static::statusLabel($this->status);
    }

    public function getTypeLabel(): string
    {
        return match ($this->type) {
            'material' => __('Material'),
            'service' => __('Service'),
            default => ucfirst($this->type),
        };
    }

    public function getLocationDisplay(): string
    {
        if ($this->isProjectLevel()) {
            return __('Project Level');
        }

        return $this->jobSite?->job_site_name ?? __('Unknown');
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeOpen($query)
    {
        return $query->whereNotIn('status', ['converted', 'cancelled']);
    }

    /**
     * Narrow a cross-project list to what this person may open.
     *
     * The requisition and quotation screens have always been reached through a
     * project or job-site route, whose middleware does the confining. The
     * purchasing queue has no project of its own, so there is no route to
     * guard and the list itself has to filter — a guard answers "may you open
     * this record?", only a filter answers "which records may you see?".
     *
     * Same shape as Rfi::visibleTo, deliberately: two lists that mean the same
     * thing should not be read two different ways. Note there is no
     * `whereNull('project_id')` branch — unlike a task, one of these always
     * belongs to a project.
     */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if (! $user || ! $user->isActive()) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->is_admin || ! $user->isConfined()) {
            return $query;
        }

        $projectIds = [];
        $jobSiteIds = [];

        foreach (app(\App\Services\PermissionResolver::class)->membershipsOf($user) as $membership) {
            if ($membership->scopeable_type === Project::class) {
                $projectIds[] = $membership->scopeable_id;
            } elseif ($membership->scopeable_type === JobSite::class) {
                $jobSiteIds[] = $membership->scopeable_id;
            }
        }

        return $query->where(function (Builder $q) use ($projectIds, $jobSiteIds) {
            $q->whereIn('project_id', $projectIds)
                ->orWhereIn('job_site_id', $jobSiteIds);
        });
    }


    /** Rounds a person owns or collaborates on. */
    public function scopeWorkedBy($query, int $userId)
    {
        return $query->where(function ($q) use ($userId) {
            $q->where('assigned_to', $userId)
                ->orWhereHas('assignees', fn ($a) => $a->where('users.id', $userId));
        });
    }

    // =========================================================================
    // ASSIGNMENT
    // =========================================================================

    /**
     * Whether ownership can still usefully change hands.
     *
     * A converted or cancelled round is finished work; naming somebody on it
     * says something that is not true.
     */
    public function canBeAssigned(): bool
    {
        return ! in_array($this->status, ['converted', 'cancelled'], true);
    }

    /** The owner plus the collaborators — everyone with work here. */
    public function workers(): \Illuminate\Support\Collection
    {
        return collect([$this->assignedTo])
            ->concat($this->assignees)
            ->filter()
            ->unique('id')
            ->values();
    }

    public function isWorkedBy(?User $user): bool
    {
        return $user !== null && $this->workers()->contains('id', $user->id);
    }

    public function getOwnerName(): string
    {
        return $this->assignedTo?->name ?? __('Unassigned');
    }

    /**
     * Record a change of owner on the existing history.
     *
     * Same convention the requisition uses: `old_status` and `new_status` are
     * written equal, which is what marks the row as an assignment rather than
     * a status move, and the two names go in the reason.
     */
    public function recordAssignment(User $user, ?User $previous, ?User $current): void
    {
        // Booleans, not the models themselves: match (true) compares strictly.
        $reason = match (true) {
            $current !== null && $previous !== null => __('Reassigned from :old to :new.', ['old' => $previous->name, 'new' => $current->name]),
            $current !== null => __('Assigned to :new.', ['new' => $current->name]),
            $previous !== null => __('Unassigned from :old.', ['old' => $previous->name]),
            default => __('Assignment cleared.'),
        };

        $this->statusHistories()->create([
            'old_status' => $this->status,
            'new_status' => $this->status,
            'changed_by' => $user->id,
            'reason' => $reason,
        ]);
    }

    /** Somebody joined or left the round's working party. */
    public function recordCollaboratorChange(User $user, User $subject, bool $added): void
    {
        $this->statusHistories()->create([
            'old_status' => $this->status,
            'new_status' => $this->status,
            'changed_by' => $user->id,
            'reason' => $added
                ? __(':name joined the round.', ['name' => $subject->name])
                : __(':name was taken off the round.', ['name' => $subject->name]),
        ]);
    }

    /**
     * Whether the response deadline has moved since the warnings went out.
     *
     * A stamp that survived the change would silently disarm the reminder for
     * good, so pushing the date re-arms both.
     */
    public function dueWarningsShouldReArm(): bool
    {
        // A record being inserted has no previous deadline, so nothing can have
        // moved — and on an insert every attribute reads as dirty, which would
        // otherwise wipe stamps that were set in the same breath.
        if (! $this->exists) {
            return false;
        }

        // Deliberately NOT gated on the in-memory stamps. A model instance
        // loaded before the reminder command ran still believes both are null,
        // and would then leave the stored stamps behind — disarming the
        // warning for good. Whether the deadline moved is the only question
        // that matters; clearing an already-null stamp costs nothing.
        if (! $this->isDirty('responses_due_at')) {
            return false;
        }

        // `isDirty` alone is not enough: the column is cast to `date`, and a
        // value that was set from a Carbon carrying a time reads as changed on
        // the very next save even though the deadline did not move. Compare
        // the dates themselves, which is what "the deadline moved" means.
        $original = $this->getOriginal('responses_due_at');

        return ($original ? \Illuminate\Support\Carbon::parse($original)->toDateString() : null)
            !== $this->responses_due_at?->toDateString();
    }
}
