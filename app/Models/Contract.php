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
        'notes',
        'contract_file_path',
        'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
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
        $last = static::max('contract_number');

        if (!$last) {
            return 'CTR-0001';
        }

        $number = (int) str_replace('CTR-', '', $last);

        return 'CTR-' . str_pad($number + 1, 4, '0', STR_PAD_LEFT);
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

    public function latestPayment(): HasOne
    {
        return $this->hasOne(ContractPayment::class)->latestOfMany('payment_date');
    }

    public function getChangeOrdersTotal(): float
    {
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
                    'code_display' => $budgetItem ? ($budgetItem->code . ' - ' . $budgetItem->name) : __('Unassigned'),
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
