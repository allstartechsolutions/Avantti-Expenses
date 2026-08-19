<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
    ];

    protected $casts = [
        'needed_by' => 'date',
        'responses_due_at' => 'date',
        'awarded_at' => 'datetime',
        'is_split_award' => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

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

    // =========================================================================
    // NUMBERING
    // =========================================================================

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

    /** Three proposals is the Brazilian norm; two is the floor. */
    public function meetsProposalMinimum(): bool
    {
        return $this->respondedCount() >= 2;
    }

    public function meetsProposalNorm(): bool
    {
        return $this->respondedCount() >= 3;
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

    public function getStatusLabel(): string
    {
        return match ($this->status) {
            'draft' => __('Draft'),
            'sent' => __('Sent to Vendors'),
            'comparing' => __('Comparing'),
            'negotiating' => __('Negotiating'),
            'awarded' => __('Awarded'),
            'converted' => __('Converted'),
            'cancelled' => __('Cancelled'),
            default => ucfirst($this->status),
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
}
