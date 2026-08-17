<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class ContractScheduleItem extends Model
{
    /**
     * Fields whose changes are recorded in contract_schedule_changes.
     * sort_order is excluded on purpose — reordering is layout, not a
     * change to the agreed schedule.
     */
    protected const AUDITED_FIELDS = [
        'description',
        'trigger_type',
        'due_date',
        'budget_item_id',
        'percent',
        'amount',
        'notes',
        'released_at',
        'release_notes',
    ];

    protected $fillable = [
        'contract_id',
        'sort_order',
        'description',
        'trigger_type',
        'due_date',
        'budget_item_id',
        'percent',
        'amount',
        'notes',
        'released_at',
        'released_by',
        'release_notes',
    ];

    protected $casts = [
        'due_date' => 'date',
        'percent' => 'decimal:2',
        'released_at' => 'datetime',
    ];

    protected function amount(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value === null ? null : round($value / 100, 2),
            set: fn ($value) => $value === null ? null : round($value * 100),
        );
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function budgetItem(): BelongsTo
    {
        return $this->belongsTo(BudgetItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(ContractPayment::class);
    }

    public function measurements(): HasMany
    {
        return $this->hasMany(ContractMeasurement::class);
    }

    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }

    public function changeLogs(): HasMany
    {
        return $this->hasMany(ContractScheduleChange::class)->latest('id');
    }

    protected static function booted(): void
    {
        // Exactly one of percent / amount: a zero percent (numeric input
        // defaulting to 0) normalizes to null; setting both is a caller
        // bug that must surface, not be silently resolved.
        static::saving(function (self $item) {
            if ((float) $item->percent <= 0) {
                $item->percent = null;
            }
            if ($item->percent !== null && $item->amount !== null) {
                throw new \LogicException('An installment must have either a percent or a fixed amount, not both.');
            }
        });

        // The agreed terms are immutable once money or a medição is
        // attached — enforced here so no future caller can bypass the
        // component-level guard.
        static::updating(function (self $item) {
            if (! $item->isDirty('amount') && ! $item->isDirty('percent') && ! $item->isDirty('trigger_type')) {
                return;
            }

            if ($item->payments()->exists() || $item->measurements()->exists()) {
                throw new \LogicException('The value and trigger of an installment with linked payments or measurements cannot be changed.');
            }
        });

        static::created(function (self $item) {
            $item->logChange('created', $item->auditableValues());
        });

        static::updated(function (self $item) {
            $dirty = array_intersect(array_keys($item->getDirty()), self::AUDITED_FIELDS);

            if ($dirty === []) {
                return;
            }

            $changes = [];
            foreach ($dirty as $field) {
                $changes[$field] = [
                    'old' => $item->auditValue($field, $item->getOriginal($field)),
                    'new' => $item->auditValue($field, $item->{$field}),
                ];
            }

            // A release — and the undo of a mistaken one — is its own
            // auditable event, not a generic edit.
            $action = 'updated';
            if (array_key_exists('released_at', $changes)) {
                $action = $item->released_at !== null ? 'released' : 'release_reverted';
            }

            $item->logChange($action, $changes);
        });

        // deleting (not deleted): the row must still exist so the change
        // record can reference it; nullOnDelete detaches the FK after.
        static::deleting(function (self $item) {
            $item->logChange('deleted', $item->auditableValues());
        });
    }

    protected function auditableValues(): array
    {
        $values = [];
        foreach (self::AUDITED_FIELDS as $field) {
            $value = $this->auditValue($field, $this->{$field});
            if ($value !== null) {
                $values[$field] = $value;
            }
        }

        return $values;
    }

    protected function auditValue(string $field, $value)
    {
        if ($value instanceof \Carbon\CarbonInterface) {
            return $field === 'due_date' ? $value->format('Y-m-d') : $value->format('Y-m-d H:i');
        }

        return $value;
    }

    protected function logChange(string $action, ?array $changes = null): void
    {
        ContractScheduleChange::create([
            'contract_id' => $this->contract_id,
            'contract_schedule_item_id' => $this->id,
            'item_description' => $this->description,
            'action' => $action,
            'changes' => $changes ?: null,
            'changed_by' => Auth::id(),
        ]);
    }

    public function isPercentBased(): bool
    {
        return (float) $this->percent > 0;
    }

    /**
     * Percent-based parcelas are never stored as a value: they compute
     * from the adjusted contract amount so change orders re-flow
     * automatically without stale snapshots.
     */
    public function getScheduledAmount(): float
    {
        if ($this->isPercentBased()) {
            return round($this->contract->getAdjustedAmount() * (float) $this->percent / 100, 2);
        }

        return (float) ($this->amount ?? 0);
    }

    /**
     * Gross value settled toward the parcela: cash paid plus the
     * retention withheld on its medições. This is what status and
     * balance compare against the (gross) scheduled amount, so a
     * medição-paid parcela reads as quitada even though the retention
     * is only released at the end of the contract.
     *
     * Each payment counts through exactly one path: a payment carrying
     * a contract_measurement_id belongs to that medição's parcela, so
     * the direct bucket only takes payments with no medição link —
     * a dual-linked payment can never count twice (not even across two
     * different parcelas). Retention releases are contract-level cash
     * and never count toward a parcela.
     */
    public function getSettledAmount(): float
    {
        $this->loadMissing(['payments', 'measurements.payments']);

        $direct = $this->payments
            ->reject(fn ($payment) => $payment->is_retention_release
                || $payment->contract_measurement_id !== null)
            ->sum(fn ($payment) => $payment->getRawOriginal('amount'));

        $viaMeasurements = $this->measurements->sum(fn ($measurement) => $measurement->getSettledAmountRaw());

        return round(($direct + $viaMeasurements) / 100, 2);
    }

    public function getBalance(): float
    {
        return round($this->getScheduledAmount() - $this->getSettledAmount(), 2);
    }

    public function isReleased(): bool
    {
        return $this->released_at !== null;
    }

    /**
     * Approval (liberação) of a parcela: the responsible user confirms
     * it may be paid, which is what puts it on the contract's payment
     * dropdown. For an evento this is the vistoria confirming the etapa
     * is concluded; for a date parcela it is the release of the
     * installment at (or near) its vencimento. Nothing is payable until
     * it has been approved.
     */
    public function release(User $user, ?string $notes = null): void
    {
        if ($this->isReleased()) {
            throw new \LogicException('This installment has already been released.');
        }

        $this->update([
            'released_at' => now(),
            'released_by' => $user->id,
            'release_notes' => $notes ?: null,
        ]);
    }

    /**
     * Undo a mistaken approval, putting the parcela back to pending.
     * Only while nothing is settled against it: once a payment or a
     * medição is linked the approval is history, and the payment must
     * be removed first.
     */
    public function revertRelease(): void
    {
        if (! $this->isReleased()) {
            throw new \LogicException('This installment is not released.');
        }

        if ($this->payments()->exists() || $this->measurements()->exists()) {
            throw new \LogicException('The approval of an installment with linked payments or measurements cannot be reverted.');
        }

        $this->update([
            'released_at' => null,
            'released_by' => null,
            'release_notes' => null,
        ]);
    }

    /**
     * Payable: a parcela is only ever due once it has been approved —
     * the vistoria for an evento, the liberação for a date parcela — or
     * once an approved medição releases it. A vencimento arriving on its
     * own does not make money payable (it only makes the parcela late,
     * see isDelayed()).
     */
    public function isDue(): bool
    {
        if ($this->isReleased()) {
            return true;
        }

        $this->loadMissing('measurements');

        return $this->measurements->contains(fn ($measurement) => $measurement->isApproved());
    }

    /**
     * Delayed: the planned date (vencimento for date parcelas, data
     * prevista for eventos) has passed and the parcela is not fully
     * settled. Partial payments keep a parcela delayed.
     */
    public function isDelayed(): bool
    {
        if ($this->due_date === null || $this->due_date->gte(today())) {
            return false;
        }

        $scheduled = $this->getScheduledAmount();

        // Nothing payable (e.g. adjusted amount zeroed by deductive
        // change orders) means nothing can be late.
        if ($scheduled <= 0) {
            return false;
        }

        return $this->getSettledAmount() < $scheduled - 0.009;
    }

    public function getDelayDays(): int
    {
        return $this->isDelayed() ? (int) $this->due_date->diffInDays(today()) : 0;
    }

    /**
     * Status is derived, never stored, so payment and medição activity
     * can never leave it out of sync.
     */
    public function getStatus(): string
    {
        $scheduled = $this->getScheduledAmount();
        $paid = $this->getSettledAmount();

        if ($scheduled > 0 && $paid >= $scheduled - 0.009) {
            return 'paid';
        }

        if ($paid > 0) {
            return 'partially_paid';
        }

        if ($scheduled <= 0) {
            return 'pending';
        }

        return $this->isDue() ? 'due' : 'pending';
    }

    public function getStatusLabel(): string
    {
        return match ($this->getStatus()) {
            'pending' => __('Pending'),
            'due' => __('Due'),
            'partially_paid' => __('Partially Paid'),
            'paid' => __('Paid'),
            default => ucfirst($this->getStatus()),
        };
    }

    public function getStatusColor(): string
    {
        return match ($this->getStatus()) {
            'pending' => 'gray',
            'due' => 'amber',
            'partially_paid' => 'blue',
            'paid' => 'green',
            default => 'gray',
        };
    }
}
