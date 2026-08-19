<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * One invited vendor = one proposal.
 *
 * The vendors answer by e-mail, so this row records how they were asked and
 * how the answer arrived; their PDF proposal attaches here, not to the round.
 */
class QuotationVendor extends Model
{
    protected $fillable = [
        'quotation_id',
        'vendor_id',
        'status',
        'invited_at',
        'invite_method',
        'invited_email',
        'source',
        'received_at',
        'responded_at',
        'proposal_valid_until',
        'lead_time_days',
        'payment_terms',
        'freight_type',
        'freight_amount',
        'discount_amount',
        'tax_amount',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'invited_at' => 'datetime',
        'received_at' => 'datetime',
        'responded_at' => 'datetime',
        'proposal_valid_until' => 'date',
        'lead_time_days' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();

        static::deleting(function (QuotationVendor $row) {
            $row->attachments->each->delete();
        });
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuotationVendorItem::class);
    }

    public function negotiations(): HasMany
    {
        return $this->hasMany(QuotationNegotiation::class)->orderBy('round');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')->latest();
    }

    // =========================================================================
    // ACCESSORS (Cents ↔ Currency)
    // =========================================================================

    protected function freightAmount(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => round($value / 100, 2),
            set: fn ($value) => round($value * 100),
        );
    }

    protected function discountAmount(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => round($value / 100, 2),
            set: fn ($value) => round($value * 100),
        );
    }

    protected function taxAmount(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => round($value / 100, 2),
            set: fn ($value) => round($value * 100),
        );
    }

    // =========================================================================
    // THE MONEY — what this proposal really costs us
    //
    // Comparing raw line prices is the mistake BR practice warns about, so the
    // total the screens show is the equalized one: the lines plus freight and
    // taxes, less the discount. Lines the vendor cannot supply count as zero
    // and are flagged instead of silently lowering the total.
    // =========================================================================

    public function itemsSubtotal(): float
    {
        return round((float) $this->items
            ->where('is_unavailable', false)
            ->sum(fn ($item) => (float) $item->total_amount), 2);
    }

    public function equalizedTotal(): float
    {
        return round(
            $this->itemsSubtotal()
            + (float) $this->freight_amount
            + (float) $this->tax_amount
            - (float) $this->discount_amount,
            2
        );
    }

    public function hasPrices(): bool
    {
        return $this->items->isNotEmpty();
    }

    public function unavailableCount(): int
    {
        return $this->items->where('is_unavailable', true)->count();
    }

    public function substituteCount(): int
    {
        return $this->items->filter(fn ($item) => $item->isSubstitute())->count();
    }

    /** Lines this vendor actually answered — priced or refused. */
    public function answeredCount(): int
    {
        return $this->items->count();
    }

    /** Lines the vendor simply did not quote. Blank is not zero. */
    public function unquotedCount(int $scopeLineCount): int
    {
        return max(0, $scopeLineCount - $this->answeredCount());
    }

    /** A proposal that does not cover the whole scope cannot be compared line for line. */
    public function coversScope(int $scopeLineCount): bool
    {
        return $this->items->where('is_unavailable', false)->count() >= $scopeLineCount;
    }

    // =========================================================================
    // NEGOTIATION
    // =========================================================================

    public function negotiationRounds(): int
    {
        return $this->negotiations->count();
    }

    public function hasBeenNegotiated(): bool
    {
        return $this->negotiations->isNotEmpty();
    }

    /** What the vendor first offered, before any haggling. */
    public function openingTotal(): ?float
    {
        return $this->hasBeenNegotiated()
            ? (float) $this->negotiations->first()->previous_total
            : null;
    }

    /** What the negotiation won in total, across every round. */
    public function negotiatedSaving(): float
    {
        $opening = $this->openingTotal();

        return $opening === null ? 0.0 : round($opening - $this->equalizedTotal(), 2);
    }

    /** Once prices are in, the proposal is on the table. */
    public function hasResponded(): bool
    {
        return in_array($this->status, ['responded', 'awarded', 'rejected'], true);
    }

    // =========================================================================
    // DISPLAY HELPERS
    // =========================================================================

    public function getStatusColor(): string
    {
        return match ($this->status) {
            'invited' => 'gray',
            'responded' => 'blue',
            'declined' => 'red',
            'awarded' => 'green',
            'rejected' => 'gray',
            default => 'gray',
        };
    }

    public function getStatusLabel(): string
    {
        return match ($this->status) {
            'invited' => __('Invited'),
            'responded' => __('Responded'),
            'declined' => __('Declined'),
            'awarded' => __('Awarded'),
            'rejected' => __('Not Selected'),
            default => ucfirst($this->status),
        };
    }

    public function getInviteMethodLabel(): ?string
    {
        return match ($this->invite_method) {
            'email' => __('E-mail'),
            'whatsapp' => __('WhatsApp'),
            'phone' => __('Phone'),
            'in_person' => __('In person'),
            default => null,
        };
    }

    public function getSourceLabel(): ?string
    {
        return match ($this->source) {
            'email' => __('E-mail'),
            'whatsapp' => __('WhatsApp'),
            'phone' => __('Phone'),
            'in_person' => __('In person'),
            default => null,
        };
    }

    /** The address the RFQ should go to, in the order the vendor record fills it. */
    public function bestEmail(): ?string
    {
        return $this->invited_email
            ?: ($this->vendor?->email ?: $this->vendor?->contact_email);
    }

    public function getFreightTypeLabel(): ?string
    {
        return match ($this->freight_type) {
            'cif' => __('CIF — the vendor pays the freight'),
            'fob' => __('FOB — we pay the freight'),
            default => null,
        };
    }

    public function proposalExpired(): bool
    {
        return $this->proposal_valid_until
            && $this->proposal_valid_until->isPast()
            && in_array($this->status, ['responded', 'rejected'], true);
    }
}
