<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A change to the work agreed with the client, at project or job site level.
 *
 * It carries two sides with independent amounts, and since 1 Sep 2026 both
 * wait for the same signal:
 *  - `amount` is the revenue side: what the client is now billed. **Only an
 *    approved change order counts towards the contract value.** It used to
 *    count whatever its status, which meant an offer the client had rejected
 *    still moved the project's contract value and its profit.
 *  - `items` are the cost side: what the change does to each cost code's
 *    budget. Only an approved change order revises the budget.
 *
 * What is still awaiting a decision is reported beside the contract value, by
 * `Project::getPendingChangeOrdersTotal()` and its job-site twin — held back
 * from the total, never hidden from the screen.
 *
 * The difference between the two is the margin on the change. It is shown,
 * never enforced — a change order sold at a loss is a fact, not a validation
 * error.
 */
class ChangeOrder extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
    ];

    protected $fillable = [
        'project_id',
        'job_site_id',
        'co_number',
        'title',
        'requested_date',
        'status',
        'approved_at',
        'approved_by',
        'description',
        'amount',
        'file_path',
        'created_by',
    ];

    protected $casts = [
        'requested_date' => 'date',
        'approved_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The RFI this change order was raised from, when it came out of one.
     *
     * The link lives on `rfis.change_order_id`, so this reads back through it
     * rather than adding a column here: an aditivo is a first-class record
     * that mostly has nothing to do with RFIs, and should not carry a column
     * for every module that may point at it.
     */
    public function rfi(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Rfi::class, 'change_order_id');
    }

    /**
     * Get/Set amount as dollars (stored as signed cents). Negative is a
     * deductive change order.
     */
    protected function amount(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => round($value / 100, 2),
            set: fn ($value) => round($value * 100),
        );
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /**
     * Get the project that owns this change order
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the job site that owns this change order
     */
    public function jobSite(): BelongsTo
    {
        return $this->belongsTo(JobSite::class);
    }

    /**
     * Get the user who created this change order
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who approved this change order
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the cost impact lines (one per cost code).
     */
    public function items(): HasMany
    {
        return $this->hasMany(ChangeOrderItem::class)->orderBy('sort_order');
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    /** Change orders that revise the cost budget. */
    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /** Change orders still waiting on a decision. */
    public function scopePendingDecision($query)
    {
        return $query->whereIn('status', [self::STATUS_DRAFT, self::STATUS_PENDING]);
    }

    // =========================================================================
    // STATUS
    // =========================================================================

    /**
     * The status as a person reads it. The stored value is never printed —
     * `draft` is not a word this product shows anybody, and pt_BR needs its
     * own. The static form labels a filter value or a report row where there
     * is no instance to ask. An aditivo is masculine, so the shared masculine
     * status words are the right ones.
     */
    public function getStatusLabel(): string
    {
        return static::statusLabel($this->status);
    }

    public static function statusLabel(?string $value): string
    {
        return match ($value) {
            self::STATUS_DRAFT => __('Draft'),
            self::STATUS_PENDING => __('Pending'),
            self::STATUS_APPROVED => __('Approved'),
            self::STATUS_REJECTED => __('Rejected'),
            default => (string) $value,
        };
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    /**
     * Approve the change order, which is what puts its cost lines into the
     * budget. Returns false if it was approved already.
     */
    public function approve(User $approver): bool
    {
        if ($this->isApproved()) {
            return false;
        }

        $this->status = self::STATUS_APPROVED;
        $this->approved_at = now();
        $this->approved_by = $approver->id;

        return $this->save();
    }

    /**
     * Reject the change order, taking its cost lines back out of the budget.
     */
    public function reject(User $user): bool
    {
        if ($this->isRejected()) {
            return false;
        }

        $this->status = self::STATUS_REJECTED;
        $this->approved_at = null;
        $this->approved_by = null;

        return $this->save();
    }

    /**
     * Send the change order back to pending, so an approval can be undone
     * without losing the record.
     */
    public function returnToPending(): bool
    {
        $this->status = self::STATUS_PENDING;
        $this->approved_at = null;
        $this->approved_by = null;

        return $this->save();
    }

    // =========================================================================
    // MONEY
    // =========================================================================

    /**
     * The total cost impact across every cost code, in dollars. Signed.
     */
    public function getCostImpactAttribute(): float
    {
        $cents = $this->relationLoaded('items')
            ? $this->items->sum(fn (ChangeOrderItem $item) => (int) $item->getRawOriginal('amount'))
            : (int) $this->items()->sum('amount');

        return round($cents / 100, 2);
    }

    /**
     * What approving this change commits, in cents, for an approval ceiling.
     *
     * The **cost** side, not the revenue side: the ceiling is about what
     * somebody may commit the company to spending. A deductive change order
     * takes money out, and taking money out of a budget is not an act a
     * spending ceiling should refuse, so the magnitude is what is compared.
     */
    public function costImpactInCents(): int
    {
        return (int) round(abs($this->cost_impact) * 100);
    }

    /**
     * Whether undoing this would pull money back out of a live budget.
     *
     * A pending change order's lines are not in the budget yet, so turning it
     * down costs nothing and is an ordinary review decision. An approved one's
     * lines are, and taking them back out is the narrower act.
     */
    public function undoingAffectsBudget(): bool
    {
        return $this->isApproved();
    }

    /**
     * Revenue minus cost: what this change is worth to the company.
     */
    public function getMarginAttribute(): float
    {
        return round($this->amount - $this->cost_impact, 2);
    }

    /**
     * Margin as a percentage of the revenue side. Null when the change order
     * bills nothing (an internal budget move), where a percentage is meaningless.
     */
    public function getMarginPercentAttribute(): ?float
    {
        if (abs($this->amount) < 0.01) {
            return null;
        }

        return round($this->margin / $this->amount * 100, 2);
    }

    /**
     * Whether the cost side has been broken down at all. Change orders created
     * before cost lines existed have none.
     */
    public function hasCostLines(): bool
    {
        return $this->relationLoaded('items')
            ? $this->items->isNotEmpty()
            : $this->items()->exists();
    }

    /**
     * Check if this is a project-level change order (not assigned to a job site)
     */
    public function isProjectLevel(): bool
    {
        return is_null($this->job_site_id);
    }

    /**
     * The budget this change order's cost lines belong to, if one exists for
     * its location. Deliberately not named budget(): Eloquent would take a
     * method of that name for a relationship.
     */
    public function resolveBudget(): ?Budget
    {
        return Budget::where('project_id', $this->project_id)
            ->where('job_site_id', $this->job_site_id)
            ->first();
    }
}
