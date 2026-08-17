<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Auth;

class Contract extends Model
{
    protected $fillable = [
        'project_id',
        'job_site_id',
        'subcontractor_id',
        'subcontractor_employee_id',
        'contract_number',
        'status',
        'start_date',
        'end_date',
        'amount',
        'retention_percent',
        'notes',
        'contract_file_path',
        'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'retention_percent' => 'decimal:2',
    ];

    protected function amount(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => round($value / 100, 2),
            set: fn ($value) => round($value * 100),
        );
    }

    public static function generateContractNumber(): string
    {
        // Numeric max, not string max: 'CTR-9999' > 'CTR-10000' lexically.
        $number = (int) static::query()
            ->selectRaw('MAX(CAST(SUBSTRING(contract_number, 5) AS UNSIGNED)) AS max_number')
            ->value('max_number');

        return 'CTR-'.str_pad($number + 1, 4, '0', STR_PAD_LEFT);
    }

    public function recordStatusChange($user, ?string $oldStatus, string $newStatus, ?string $reason = null): void
    {
        $this->statusHistories()->create([
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'changed_by' => $user->id,
            'reason' => $reason,
        ]);
    }

    public function isProjectLevel(): bool
    {
        return is_null($this->job_site_id);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isPartiallyPaid(): bool
    {
        return $this->status === 'partially_paid';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function jobSite(): BelongsTo
    {
        return $this->belongsTo(JobSite::class);
    }

    public function subcontractor(): BelongsTo
    {
        return $this->belongsTo(Subcontractor::class);
    }

    public function subcontractorEmployee(): BelongsTo
    {
        return $this->belongsTo(SubcontractorEmployee::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(ContractStatusHistory::class);
    }

    public function changeOrders(): HasMany
    {
        return $this->hasMany(ContractChangeOrder::class)->orderByDesc('date');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(ContractPayment::class)->orderByDesc('payment_date');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(ContractBudgetAllocation::class);
    }

    public function scheduleItems(): HasMany
    {
        return $this->hasMany(ContractScheduleItem::class)->orderBy('sort_order');
    }

    public function measurements(): HasMany
    {
        return $this->hasMany(ContractMeasurement::class)->orderByDesc('measurement_number');
    }

    public function scheduleChanges(): HasMany
    {
        return $this->hasMany(ContractScheduleChange::class)->latest('id');
    }

    public function latestPayment(): HasOne
    {
        return $this->hasOne(ContractPayment::class)->latestOfMany('payment_date');
    }

    public function getChangeOrdersTotal(): float
    {
        if ($this->relationLoaded('changeOrders')) {
            return round($this->changeOrders->sum(fn ($co) => $co->getRawOriginal('amount')) / 100, 2);
        }

        return round($this->changeOrders()->sum('amount') / 100, 2);
    }

    public function getAdjustedAmount(): float
    {
        return round($this->amount + $this->getChangeOrdersTotal(), 2);
    }

    public function getAmountPaid(): float
    {
        return round($this->payments()->sum('amount') / 100, 2);
    }

    public function getBalanceDue(): float
    {
        return round($this->getAdjustedAmount() - $this->getAmountPaid(), 2);
    }

    public function getAllocatedTotal(): float
    {
        return round($this->allocations()->sum('amount') / 100, 2);
    }

    public function getUnallocatedAmount(): float
    {
        return round($this->amount - $this->getAllocatedTotal(), 2);
    }

    public function hasRetention(): bool
    {
        return (float) $this->retention_percent > 0;
    }

    /**
     * Retention actually withheld so far: proportional to the cash paid
     * on each approved medição, so an approved-but-unpaid medição holds
     * nothing yet. Payments are recorded net, so this money has not left
     * the balance due — the contract stays partially_paid until the
     * liberação de retenção.
     *
     * Sums over loaded relations: callers that mutate payments or
     * medições in the same request must refresh()/unset the relations
     * first (the codebase convention after any payment write).
     */
    public function getRetentionHeld(): float
    {
        $this->loadMissing('measurements.payments');

        return round($this->measurements
            ->filter(fn ($measurement) => $measurement->isApproved())
            ->sum(fn ($measurement) => $measurement->getRetentionWithheldRaw()) / 100, 2);
    }

    public function getRetentionReleased(): float
    {
        return round($this->payments()->where('is_retention_release', true)->sum('amount') / 100, 2);
    }

    /**
     * Floored at zero: an over-release (or a medição removed after its
     * retention was released) is a data problem to surface in audits,
     * not a negative balance to show on the contract card. Release
     * actions must cap the amount at this value before saving.
     */
    public function getRetentionOutstanding(): float
    {
        return round(max(0, $this->getRetentionHeld() - $this->getRetentionReleased()), 2);
    }

    /**
     * Retention still held per cost code, in cents: what each medição
     * withheld (split across its items by their period amount) minus
     * what previous liberações already gave back to that code. Feeds the
     * proportional itemization of a retention release so the SOV grid
     * keeps adding up per code. Items without a cost code are left out —
     * the grid's fallback bucket absorbs unitemized cash.
     *
     * @return array<int, int> budget_item_id => cents
     */
    public function getRetentionOutstandingByCostCode(): array
    {
        $this->loadMissing(['measurements.items', 'measurements.payments', 'payments.items']);

        $byCode = [];

        foreach ($this->measurements as $measurement) {
            $withheld = $measurement->getRetentionWithheldRaw();
            $periodTotal = (int) $measurement->items->sum(fn ($item) => $item->getRawOriginal('period_amount'));

            if ($withheld <= 0 || $periodTotal <= 0) {
                continue;
            }

            foreach ($measurement->items as $item) {
                if ($item->budget_item_id === null) {
                    continue;
                }

                $share = (int) round($withheld * (int) $item->getRawOriginal('period_amount') / $periodTotal);
                $byCode[$item->budget_item_id] = ($byCode[$item->budget_item_id] ?? 0) + $share;
            }
        }

        foreach ($this->payments as $payment) {
            if (! $payment->is_retention_release) {
                continue;
            }

            foreach ($payment->items as $item) {
                if ($item->budget_item_id === null) {
                    continue;
                }

                $byCode[$item->budget_item_id] = ($byCode[$item->budget_item_id] ?? 0) - (int) $item->getRawOriginal('amount');
            }
        }

        return array_filter($byCode, fn (int $cents) => $cents > 0);
    }

    /**
     * Total of the cronograma parcelas. Percent-based parcelas compute
     * against the adjusted amount, so this re-flows with change orders.
     * Shares this instance (and its loaded change orders) with each item
     * so percent rows don't lazy-load a fresh Contract per row.
     */
    public function getScheduledTotal(): float
    {
        $this->loadMissing(['scheduleItems', 'changeOrders']);
        $this->scheduleItems->each(fn ($item) => $item->setRelation('contract', $this));

        return round($this->scheduleItems->sum(fn ($item) => $item->getScheduledAmount()), 2);
    }

    public function getUnscheduledAmount(): float
    {
        return round($this->getAdjustedAmount() - $this->getScheduledTotal(), 2);
    }

    /**
     * Contract money the cronograma does not cover and that has not been
     * paid off-schedule yet — a partial cronograma, or a change order
     * raising the adjusted amount after the parcelas were agreed. This
     * much may be paid without naming a parcela; otherwise it could never
     * be paid at all. Shared by the contract page and the payment batch
     * so both gates agree.
     */
    public function getUnscheduledRemaining(): float
    {
        $unscheduled = $this->getUnscheduledAmount();

        if ($unscheduled <= 0) {
            return 0.0;
        }

        $paidOffSchedule = round($this->payments()
            ->whereNull('contract_schedule_item_id')
            ->whereNull('contract_measurement_id')
            ->where('is_retention_release', false)
            ->sum('amount') / 100, 2);

        return round(max(0, min($unscheduled - $paidOffSchedule, $this->getBalanceDue())), 2);
    }

    /**
     * What this contract still owes, placed on the calendar — the single
     * definition shared by the payment schedule and accounts payable
     * reports:
     *   - every parcela still owing, due on its own date (vencimento for
     *     date parcelas, data prevista for eventos);
     *   - whatever the cronograma does not cover — the unscheduled
     *     remainder, or the whole balance of a contract with no
     *     cronograma — due on the contract's END DATE.
     * A parcela with no date of its own falls back to the end date too,
     * and the date stays null when the contract has no end date (the
     * caller decides where undated money goes).
     *
     * The items always add up to the contract's balance due — parcelas
     * are scaled down when they exceed it (money paid without settling a
     * parcela, or a cronograma scheduling more than the contract) — so
     * the reports can neither over-state nor lose payables.
     *
     * @return array<int, array{date: ?\Carbon\CarbonInterface, amount: float, label: string, scheduled: bool}>
     */
    public function openPayableItems(): array
    {
        $this->loadMissing(['changeOrders', 'payments', 'scheduleItems.payments', 'scheduleItems.measurements.payments']);

        // Share this instance so percent-based parcelas don't lazy-load a
        // Contract (and its change orders) per row.
        $this->scheduleItems->each(fn (ContractScheduleItem $item) => $item->setRelation('contract', $this));

        $paid = round($this->payments->sum(fn (ContractPayment $payment) => $payment->getRawOriginal('amount')) / 100, 2);
        $balanceDue = round($this->getAdjustedAmount() - $paid, 2);

        $items = [];
        $scheduledOpen = 0.0;

        foreach ($this->scheduleItems as $item) {
            $balance = $item->getBalance();

            if ($balance <= 0.009) {
                continue;
            }

            $scheduledOpen = round($scheduledOpen + $balance, 2);
            $items[] = [
                'date' => $item->due_date ?? $this->end_date,
                'amount' => $balance,
                'label' => $item->description,
                'scheduled' => true,
            ];
        }

        $unscheduled = round($balanceDue - $scheduledOpen, 2);

        if ($unscheduled > 0.009) {
            $items[] = [
                'date' => $this->end_date,
                'amount' => $unscheduled,
                'label' => $items === [] ? __('Contract balance') : __('Unscheduled balance'),
                'scheduled' => false,
            ];

            return $items;
        }

        // The parcelas can add up to more than the contract still owes:
        // money paid without settling one (a payment made before the
        // cronograma existed, a payment batch, a cronograma scheduling
        // more than the adjusted amount). Scale them down to the real
        // balance so the reports can never over-state what is payable.
        if ($balanceDue <= 0.009) {
            return [];
        }

        if ($scheduledOpen > $balanceDue + 0.009) {
            $factor = $balanceDue / $scheduledOpen;
            $running = 0.0;

            foreach ($items as $i => $item) {
                $amount = round($item['amount'] * $factor, 2);
                $items[$i]['amount'] = $amount;
                $running = round($running + $amount, 2);
            }

            // Put any rounding crumb on the first item so the items still
            // add up to the balance due exactly.
            if ($items !== [] && abs($balanceDue - $running) >= 0.01) {
                $items[0]['amount'] = round($items[0]['amount'] + ($balanceDue - $running), 2);
            }
        }

        return $items;
    }

    /**
     * Schedule-of-values rows for this contract: one row per cost code
     * touched by an allocation, change order, or payment item, plus an
     * fallback bucket that absorbs the unallocated contract remainder,
     * change orders without a code, and payment amounts not covered by
     * line items — so pre-existing contracts and payments keep adding
     * up without any backfill. The fallback resolves to the budget's
     * default item when one is marked, otherwise to "Unassigned".
     *
     * Each row: budget_item_id, code_display, sort_code, scheduled,
     * paid, percent_complete (latest cumulative), balance.
     *
     * $paidFrom / $paidTo optionally restrict which payments count toward
     * paid / % (for date-windowed reports); scheduled is never windowed.
     */
    public function costCodeSchedule(?\Carbon\CarbonInterface $paidFrom = null, ?\Carbon\CarbonInterface $paidTo = null): \Illuminate\Support\Collection
    {
        $this->loadMissing([
            'allocations.budgetItem',
            'changeOrders.budgetItem',
            'payments.items.budgetItem',
        ]);

        $fallbackItem = Budget::where('project_id', $this->project_id)
            ->where('job_site_id', $this->job_site_id)
            ->first()
            ?->defaultItem();

        $rows = [];

        $ensureRow = function ($budgetItem) use (&$rows, $fallbackItem): int {
            $budgetItem = $budgetItem ?? $fallbackItem;
            $key = $budgetItem?->id ?? 0;
            if (! isset($rows[$key])) {
                $rows[$key] = [
                    'budget_item_id' => $budgetItem?->id,
                    'code_display' => $budgetItem ? ($budgetItem->code.' - '.$budgetItem->name) : __('Unassigned'),
                    'sort_code' => $budgetItem?->code,
                    'scheduled' => 0.0,
                    'paid' => 0.0,
                    'percent_complete' => null,
                ];
            }

            return $key;
        };

        foreach ($this->allocations as $allocation) {
            $key = $ensureRow($allocation->budgetItem);
            $rows[$key]['scheduled'] += $allocation->amount;
        }

        // Unallocated remainder of the base contract amount.
        $allocatedTotal = round($this->allocations->sum(fn ($a) => $a->getRawOriginal('amount')) / 100, 2);
        $remainder = round($this->amount - $allocatedTotal, 2);
        if (abs($remainder) >= 0.01) {
            $key = $ensureRow(null);
            $rows[$key]['scheduled'] += $remainder;
        }

        foreach ($this->changeOrders as $changeOrder) {
            $key = $ensureRow($changeOrder->budgetItem);
            $rows[$key]['scheduled'] += $changeOrder->amount;
        }

        // Payments in chronological order so the latest percent wins.
        $orderedPayments = $this->payments->sortBy([['payment_date', 'asc'], ['id', 'asc']]);
        foreach ($orderedPayments as $payment) {
            if ($paidFrom && $payment->payment_date->lt($paidFrom)) {
                continue;
            }
            if ($paidTo && $payment->payment_date->gt($paidTo)) {
                continue;
            }
            foreach ($payment->items as $item) {
                $key = $ensureRow($item->budgetItem);
                $rows[$key]['paid'] += $item->amount;
                if ($item->percent_complete !== null) {
                    $rows[$key]['percent_complete'] = (float) $item->percent_complete;
                }
            }

            $unitemized = round($payment->amount - $payment->getItemizedTotal(), 2);
            if (abs($unitemized) >= 0.01) {
                $key = $ensureRow(null);
                $rows[$key]['paid'] += $unitemized;
            }
        }

        return collect($rows)
            ->map(function (array $row) {
                $row['scheduled'] = round($row['scheduled'], 2);
                $row['paid'] = round($row['paid'], 2);
                $row['balance'] = round($row['scheduled'] - $row['paid'], 2);

                return $row;
            })
            ->sortBy([
                fn ($a, $b) => ($a['budget_item_id'] === null) <=> ($b['budget_item_id'] === null),
                fn ($a, $b) => strnatcasecmp($a['sort_code'] ?? '', $b['sort_code'] ?? ''),
            ])
            ->values();
    }

    public function updateStatusFromPayments(): void
    {
        if ($this->status === 'cancelled') {
            return;
        }

        $amountPaid = $this->getAmountPaid();
        $contractAmount = $this->getAdjustedAmount();

        if ($amountPaid >= $contractAmount) {
            $newStatus = 'paid';
        } elseif ($amountPaid > 0) {
            $newStatus = 'partially_paid';
        } else {
            $newStatus = 'completed';
        }

        if ($newStatus !== $this->status) {
            $oldStatus = $this->status;
            $this->update(['status' => $newStatus]);
            $this->recordStatusChange(
                Auth::user(),
                $oldStatus,
                $newStatus,
                'Auto-updated from payment activity'
            );
        }
    }
}
