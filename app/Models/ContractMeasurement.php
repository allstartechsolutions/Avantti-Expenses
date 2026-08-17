<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class ContractMeasurement extends Model
{
    // Mirror the DB default so a freshly created instance passes the
    // isDraft() guard without needing a refresh() round-trip.
    protected $attributes = [
        'status' => 'draft',
    ];

    protected $fillable = [
        'contract_id',
        'measurement_number',
        'period_start',
        'period_end',
        'status',
        'gross_amount',
        'retention_amount',
        'net_amount',
        'contract_schedule_item_id',
        'notes',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'approved_at' => 'datetime',
    ];

    protected function grossAmount(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => round($value / 100, 2),
            set: fn ($value) => round($value * 100),
        );
    }

    protected function retentionAmount(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => round($value / 100, 2),
            set: fn ($value) => round($value * 100),
        );
    }

    protected function netAmount(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => round($value / 100, 2),
            set: fn ($value) => round($value * 100),
        );
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ContractMeasurementItem::class);
    }

    public function scheduleItem(): BelongsTo
    {
        return $this->belongsTo(ContractScheduleItem::class, 'contract_schedule_item_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(ContractPayment::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    protected static function booted(): void
    {
        // Sequential numbering per contract. lockForUpdate only holds
        // inside a transaction, so refuse auto-numbered creation outside
        // one — create via createNumbered() (or your own transaction).
        static::creating(function (self $measurement) {
            if (! $measurement->measurement_number) {
                if (DB::transactionLevel() === 0) {
                    throw new \LogicException('Create measurements via createNumbered() so numbering is race-safe.');
                }

                $measurement->measurement_number = (int) static::where('contract_id', $measurement->contract_id)
                    ->lockForUpdate()
                    ->max('measurement_number') + 1;
            }
        });
    }

    /**
     * Canonical creation path: wraps the insert in a transaction so the
     * numbering lock in creating() holds against concurrent creates.
     */
    public static function createNumbered(array $attributes): self
    {
        return DB::transaction(fn () => static::create($attributes));
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    /**
     * Cash paid toward this medição (net of retention); liberação de
     * retenção payments are contract-level and never count here.
     */
    public function getAmountPaid(): float
    {
        $this->loadMissing('payments');

        return round($this->payments
            ->reject(fn ($payment) => $payment->is_retention_release)
            ->sum(fn ($payment) => $payment->getRawOriginal('amount')) / 100, 2);
    }

    /**
     * Net cash still owed on this medição. A fully retained medição
     * (net zero) owes nothing — approval alone settles it.
     */
    public function getRemainingNet(): float
    {
        if (! $this->isApproved()) {
            return 0.0;
        }

        return round(max(0, $this->net_amount - $this->getAmountPaid()), 2);
    }

    public function isPaid(): bool
    {
        if (! $this->isApproved()) {
            return false;
        }

        if ($this->net_amount > 0) {
            return $this->getAmountPaid() >= $this->net_amount - 0.009;
        }

        // Fully retained (net zero): nothing is payable, so approval
        // itself settles the medição.
        return $this->gross_amount > 0;
    }

    /**
     * Gross value settled by the cash received: paid amount grossed
     * back up by the retention proportion, capped at the medição's own
     * totals. Used by the cronograma to compare against gross parcelas.
     * Only approved medições settle anything — money on a draft or
     * cancelled medição is a data problem, not progress.
     */
    public function getSettledAmountRaw(): int
    {
        if (! $this->isApproved()) {
            return 0;
        }

        $net = (int) $this->getRawOriginal('net_amount');
        $gross = (int) $this->getRawOriginal('gross_amount');

        if ($net <= 0) {
            // Fully retained: settled at approval, no cash to wait for.
            return $gross;
        }

        $paid = (int) $this->payments
            ->reject(fn ($payment) => $payment->is_retention_release)
            ->sum(fn ($payment) => $payment->getRawOriginal('amount'));

        if ($paid <= 0) {
            return 0;
        }

        return (int) round(min($paid, $net) * $gross / $net) + max(0, $paid - $net);
    }

    /**
     * Retention actually withheld: proportional to the cash received,
     * so an approved-but-unpaid medição holds nothing yet. A fully
     * retained medição (net zero) withholds everything at approval.
     */
    public function getRetentionWithheldRaw(): int
    {
        if (! $this->isApproved()) {
            return 0;
        }

        $retention = (int) $this->getRawOriginal('retention_amount');
        $net = (int) $this->getRawOriginal('net_amount');

        if ($retention <= 0) {
            return 0;
        }

        if ($net <= 0) {
            return $retention;
        }

        $paid = (int) $this->payments
            ->reject(fn ($payment) => $payment->is_retention_release)
            ->sum(fn ($payment) => $payment->getRawOriginal('amount'));

        return (int) round($retention * min($paid, $net) / $net);
    }

    /**
     * Approving snapshots the totals: the retention % in force now is
     * locked in, later changes to the contract never retro-apply, and
     * the items become read-only. Only a draft can be approved — an
     * approved medição must never be re-snapshotted.
     */
    public function approve(User $user): void
    {
        if (! $this->isDraft()) {
            throw new \LogicException('Only a draft medição can be approved.');
        }

        DB::transaction(function () use ($user) {
            $gross = round($this->items()->sum('period_amount') / 100, 2);
            $retentionPercent = (float) ($this->contract->retention_percent ?? 0);
            $retention = round($gross * $retentionPercent / 100, 2);

            $this->update([
                'status' => 'approved',
                'gross_amount' => $gross,
                'retention_amount' => $retention,
                'net_amount' => round($gross - $retention, 2),
                'approved_by' => $user->id,
                'approved_at' => now(),
            ]);
        });
    }

    public function getStatusLabel(): string
    {
        return match ($this->status) {
            'draft' => __('Draft'),
            'approved' => $this->isPaid() ? __('Paid') : __('Approved'),
            'cancelled' => __('Cancelled'),
            default => ucfirst($this->status),
        };
    }

    public function getStatusColor(): string
    {
        return match ($this->status) {
            'draft' => 'gray',
            'approved' => $this->isPaid() ? 'green' : 'amber',
            'cancelled' => 'red',
            default => 'gray',
        };
    }
}
