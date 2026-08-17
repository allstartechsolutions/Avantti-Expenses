<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\Expense;
use App\Models\ExpensePayment;
use App\Models\JobSite;
use App\Models\Project;
use Carbon\Carbon;

/**
 * Payment Schedule for the project / job site financial reports.
 *
 * Answers "what have we paid, what is still open, and when is it due" —
 * system-wide (all projects), for a single project (all job sites +
 * project-level), or for a single job site:
 *   - Expenses are scheduled: installments via expense_payments.due_date,
 *     one-time expenses via COALESCE(payment_due_date, expense_date).
 *   - Overdue is DERIVED (due date < today) — nothing marks payments overdue
 *     automatically, so the stored 'overdue' status is never consulted.
 *   - Contracts are scheduled by their cronograma: each open parcela is due on
 *     its vencimento (date parcelas) or data prevista (eventos). Whatever the
 *     cronograma does not cover — the unscheduled remainder, and the whole
 *     balance of a contract without a cronograma — is due on the contract's
 *     END DATE; contracts with no end date land in a "No due date" bucket so
 *     the money is still visible instead of being dropped.
 *   - Cancelled expenses and contracts are excluded everywhere.
 *
 * The same build() payload feeds the Livewire pages and the PDF controllers
 * so screen and PDF numbers always match.
 */
class PaymentScheduleService
{
    public const PROJECTION_CAP_MONTHS = 24;

    protected Carbon $today;

    protected ?Carbon $from = null;
    protected ?Carbon $to = null;

    protected ?array $expenseScheduleCache = null;

    protected ?array $contractScheduleCache = null;

    public function __construct(
        protected ?int $projectId = null,
        protected ?int $jobSiteId = null,
        protected ?int $clientId = null,
    ) {
        $this->today = Carbon::now()->startOfDay();
    }

    public static function forProject(Project $project): self
    {
        return new self($project->id);
    }

    public static function forJobSite(JobSite $jobSite): self
    {
        return new self($jobSite->project_id, $jobSite->id);
    }

    /**
     * System-wide scope (all projects), optionally narrowed by filters.
     */
    public static function forSystem(?int $clientId = null, ?int $projectId = null, ?int $jobSiteId = null): self
    {
        return new self($projectId, $jobSiteId, $clientId);
    }

    /**
     * Limit the schedule to a date window: open items by DUE date, paid items
     * by PAID date (falling back to the due date when paid_date is missing).
     * Either bound may be null for an open-ended range.
     */
    public function between(?string $from, ?string $to): self
    {
        $this->from = $from ? Carbon::parse($from)->startOfDay() : null;
        $this->to = $to ? Carbon::parse($to)->endOfDay() : null;

        if ($this->from && $this->to && $this->to->lt($this->from)) {
            [$this->from, $this->to] = [$this->to, $this->from];
        }

        return $this;
    }

    /**
     * Full payload consumed by the report pages and PDF controllers.
     */
    public function build(): array
    {
        return [
            'expenses' => $this->expenseSchedule(),
            'contracts' => $this->contractSummary(),
            'combined' => $this->combinedSummary(),
            'projection' => $this->projection(),
        ];
    }

    // =========================================================================
    // QUERY HELPERS — each returns a fresh builder. "Open" = not yet paid with
    // a non-cancelled parent. Patterns mirror AccountsPayableService.
    // =========================================================================

    protected function applyScope($q): void
    {
        $q->when($this->projectId, fn ($q) => $q->where('project_id', $this->projectId))
            ->when($this->jobSiteId, fn ($q) => $q->where('job_site_id', $this->jobSiteId))
            ->when($this->clientId, fn ($q) => $q->whereHas('project', fn ($p) => $p->where('client_id', $this->clientId)));
    }

    protected function openInstallments()
    {
        return ExpensePayment::query()
            ->where('status', '!=', 'paid')
            ->whereHas('expense', function ($q) {
                $q->where('status', '!=', 'cancelled');
                $this->applyScope($q);
            })
            ->when($this->from, fn ($q) => $q->whereDate('due_date', '>=', $this->from->toDateString()))
            ->when($this->to, fn ($q) => $q->whereDate('due_date', '<=', $this->to->toDateString()));
    }

    protected function paidInstallments()
    {
        return ExpensePayment::query()
            ->where('status', 'paid')
            ->whereHas('expense', function ($q) {
                $q->where('status', '!=', 'cancelled');
                $this->applyScope($q);
            })
            ->when($this->from, fn ($q) => $q->whereRaw('COALESCE(paid_date, due_date) >= ?', [$this->from->toDateString()]))
            ->when($this->to, fn ($q) => $q->whereRaw('COALESCE(paid_date, due_date) <= ?', [$this->to->toDateString()]));
    }

    protected function openOneTime()
    {
        return Expense::query()
            ->where('total_installments', 1)
            ->whereNotIn('status', ['paid', 'cancelled'])
            ->tap(fn ($q) => $this->applyScope($q))
            ->when($this->from, fn ($q) => $q->whereRaw('COALESCE(payment_due_date, expense_date) >= ?', [$this->from->toDateString()]))
            ->when($this->to, fn ($q) => $q->whereRaw('COALESCE(payment_due_date, expense_date) <= ?', [$this->to->toDateString()]));
    }

    protected function paidOneTime()
    {
        return Expense::query()
            ->where('total_installments', 1)
            ->where('status', 'paid')
            ->tap(fn ($q) => $this->applyScope($q))
            ->when($this->from, fn ($q) => $q->whereRaw('COALESCE(paid_date, expense_date) >= ?', [$this->from->toDateString()]))
            ->when($this->to, fn ($q) => $q->whereRaw('COALESCE(paid_date, expense_date) <= ?', [$this->to->toDateString()]));
    }

    protected function contracts()
    {
        return Contract::query()
            ->with([
                'changeOrders',
                'payments',
                'scheduleItems.payments',
                'scheduleItems.measurements.payments',
            ])
            ->where('status', '!=', 'cancelled')
            ->tap(fn ($q) => $this->applyScope($q));
    }

    /**
     * Is a dated item inside the active window? Undated contract money
     * (no parcela date and no contract end date) cannot be matched by a
     * range, so it only shows when no range is set — the same way a
     * filter drops anything it cannot place.
     */
    protected function inRange(?Carbon $date): bool
    {
        if ($date === null) {
            return $this->from === null && $this->to === null;
        }

        if ($this->from && $date->lt($this->from->copy()->startOfDay())) {
            return false;
        }

        return ! ($this->to && $date->gt($this->to->copy()->endOfDay()));
    }

    // =========================================================================
    // SECTIONS
    // =========================================================================

    /**
     * Expense totals by due date: paid / open / overdue / upcoming.
     * Invariants: total = paid + open, open = overdue + upcoming.
     * All sums are aggregated in SQL on the cents columns and converted once.
     */
    public function expenseSchedule(): array
    {
        if ($this->expenseScheduleCache !== null) {
            return $this->expenseScheduleCache;
        }

        $today = $this->today->toDateString();

        $paidInst = $this->paidInstallments()
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(amount), 0) as total')
            ->first();
        $paidOne = $this->paidOneTime()
            ->selectRaw('COUNT(*) as cnt, COALESCE(SUM(total_amount), 0) as total')
            ->first();

        $openInst = $this->openInstallments()
            ->selectRaw(
                'COUNT(*) as cnt, COALESCE(SUM(amount), 0) as total,'
                . ' COALESCE(SUM(CASE WHEN due_date < ? THEN amount ELSE 0 END), 0) as overdue_total,'
                . ' COALESCE(SUM(CASE WHEN due_date < ? THEN 1 ELSE 0 END), 0) as overdue_cnt',
                [$today, $today]
            )
            ->first();
        $openOne = $this->openOneTime()
            ->selectRaw(
                'COUNT(*) as cnt, COALESCE(SUM(total_amount), 0) as total,'
                . ' COALESCE(SUM(CASE WHEN COALESCE(payment_due_date, expense_date) < ? THEN total_amount ELSE 0 END), 0) as overdue_total,'
                . ' COALESCE(SUM(CASE WHEN COALESCE(payment_due_date, expense_date) < ? THEN 1 ELSE 0 END), 0) as overdue_cnt',
                [$today, $today]
            )
            ->first();

        $paid = round(($paidInst->total + $paidOne->total) / 100, 2);
        $open = round(($openInst->total + $openOne->total) / 100, 2);
        $overdue = round(($openInst->overdue_total + $openOne->overdue_total) / 100, 2);

        return $this->expenseScheduleCache = [
            'paid' => $paid,
            'open' => $open,
            'overdue' => $overdue,
            'upcoming' => round($open - $overdue, 2),
            'total' => round($paid + $open, 2),
            'paid_count' => (int) $paidInst->cnt + (int) $paidOne->cnt,
            'open_count' => (int) $openInst->cnt + (int) $openOne->cnt,
            'overdue_count' => (int) $openInst->overdue_cnt + (int) $openOne->overdue_cnt,
        ];
    }

    /**
     * Contract money placed on the calendar, mirroring expenseSchedule():
     * paid (by payment date) and open (by due date) with the overdue split,
     * plus the dated open items the projection buckets.
     *
     * The split into dated items is Contract::openPayableItems() — the same
     * definition the accounts payable report uses.
     */
    public function contractSchedule(): array
    {
        if ($this->contractScheduleCache !== null) {
            return $this->contractScheduleCache;
        }

        $paid = 0.0;
        $paidCount = 0;
        $openItems = [];
        $count = 0;

        foreach ($this->contracts()->get() as $contract) {
            $count++;

            foreach ($contract->payments as $payment) {
                if (! $this->inRange($payment->payment_date)) {
                    continue;
                }

                $paid = round($paid + $payment->amount, 2);
                $paidCount++;
            }

            foreach ($contract->openPayableItems() as $item) {
                $openItems[] = ['date' => $item['date'], 'amount' => $item['amount']];
            }
        }

        $inRange = array_values(array_filter($openItems, fn (array $item) => $this->inRange($item['date'])));

        $open = 0.0;
        $overdue = 0.0;
        $overdueCount = 0;
        $undated = 0.0;
        $undatedCount = 0;

        foreach ($inRange as $item) {
            $open = round($open + $item['amount'], 2);

            if ($item['date'] === null) {
                $undated = round($undated + $item['amount'], 2);
                $undatedCount++;
            } elseif ($item['date']->lt($this->today)) {
                $overdue = round($overdue + $item['amount'], 2);
                $overdueCount++;
            }
        }

        return $this->contractScheduleCache = [
            'paid' => $paid,
            'open' => $open,
            'overdue' => $overdue,
            'upcoming' => round($open - $overdue - $undated, 2),
            'undated' => $undated,
            'total' => round($paid + $open, 2),
            'paid_count' => $paidCount,
            'open_count' => count($inRange),
            'overdue_count' => $overdueCount,
            'undated_count' => $undatedCount,
            'count' => $count,
            'items' => $inRange,
        ];
    }

    /**
     * Contract totals for the strip. Committed is paid + open so it agrees
     * with the active range; with no range that is exactly the adjusted
     * amount, as before.
     */
    public function contractSummary(): array
    {
        $contracts = $this->contractSchedule();

        return [
            'adjusted' => $contracts['total'],
            'paid' => $contracts['paid'],
            'balance' => $contracts['open'],
            'count' => $contracts['count'],
            'overdue' => $contracts['overdue'],
            'upcoming' => $contracts['upcoming'],
            'undated' => $contracts['undated'],
            'open_count' => $contracts['open_count'],
            'overdue_count' => $contracts['overdue_count'],
        ];
    }

    /**
     * Combined expenses + contracts summary.
     */
    public function combinedSummary(): array
    {
        $expenses = $this->expenseSchedule();
        $contracts = $this->contractSummary();

        return [
            'committed' => round($expenses['total'] + $contracts['adjusted'], 2),
            'paid' => round($expenses['paid'] + $contracts['paid'], 2),
            'outstanding' => round($expenses['open'] + $contracts['balance'], 2),
            'expenses' => [
                'total' => $expenses['total'],
                'paid' => $expenses['paid'],
                'outstanding' => $expenses['open'],
            ],
            'contracts' => [
                'total' => $contracts['adjusted'],
                'paid' => $contracts['paid'],
                'outstanding' => $contracts['balance'],
            ],
        ];
    }

    /**
     * Monthly projection of open payments by due date — expenses and the
     * contract cronogramas together: an Overdue bucket, one row per month
     * until the last scheduled open payment (capped), a Later bucket when the
     * cap cuts the tail off, and a No-due-date bucket for contract money with
     * no parcela date and no contract end date.
     *
     * The current month's row covers [today, end of month] so items already
     * past due this month land only in the Overdue bucket.
     */
    public function projection(): array
    {
        $today = $this->today->toDateString();
        $buckets = [];

        $expenses = $this->expenseSchedule();
        $contracts = $this->contractSchedule();

        $overdueAmount = round($expenses['overdue'] + $contracts['overdue'], 2);
        $overdueCount = $expenses['overdue_count'] + $contracts['overdue_count'];

        if ($overdueAmount > 0 || $overdueCount > 0) {
            $buckets[] = [
                'label' => 'Overdue (past due)',
                'type' => 'overdue',
                'count' => $overdueCount,
                'amount' => $overdueAmount,
            ];
        }

        // Open items due on/after today, grouped by month in SQL (2 queries).
        // Items due earlier in the current month were already bucketed as
        // overdue above, so the >= today filter implements the boundary rule.
        $centsByMonth = [];
        $countsByMonth = [];

        $instRows = $this->openInstallments()
            ->whereDate('due_date', '>=', $today)
            ->selectRaw("DATE_FORMAT(due_date, '%Y-%m') as ym, COUNT(*) as cnt, COALESCE(SUM(amount), 0) as total")
            ->groupBy('ym')
            ->get();
        $oneRows = $this->openOneTime()
            ->whereRaw('COALESCE(payment_due_date, expense_date) >= ?', [$today])
            ->selectRaw("DATE_FORMAT(COALESCE(payment_due_date, expense_date), '%Y-%m') as ym, COUNT(*) as cnt, COALESCE(SUM(total_amount), 0) as total")
            ->groupBy('ym')
            ->get();

        foreach ([$instRows, $oneRows] as $rows) {
            foreach ($rows as $row) {
                $centsByMonth[$row->ym] = ($centsByMonth[$row->ym] ?? 0) + $row->total;
                $countsByMonth[$row->ym] = ($countsByMonth[$row->ym] ?? 0) + (int) $row->cnt;
            }
        }

        // Contract parcelas (and the unscheduled remainders) due from today on.
        foreach ($contracts['items'] as $item) {
            if ($item['date'] === null || $item['date']->lt($this->today)) {
                continue;
            }

            $ym = $item['date']->format('Y-m');
            $centsByMonth[$ym] = ($centsByMonth[$ym] ?? 0) + (int) round($item['amount'] * 100);
            $countsByMonth[$ym] = ($countsByMonth[$ym] ?? 0) + 1;
        }

        $months = collect(array_keys($centsByMonth))->sort();
        $capped = false;

        if ($months->isNotEmpty()) {
            // Start at the current month or the first month with data, whichever
            // is later (a future date filter would otherwise emit leading zero rows).
            $firstDataMonth = Carbon::createFromFormat('Y-m-d', $months->first() . '-01')->startOfMonth();
            $currentMonth = $this->today->copy()->startOfMonth();
            $monthCursor = $firstDataMonth->gt($currentMonth) ? $firstDataMonth : $currentMonth;
            $lastMonth = Carbon::createFromFormat('Y-m-d', $months->last() . '-01')->startOfMonth();

            for ($i = 0; $monthCursor->lte($lastMonth); $i++, $monthCursor->addMonth()) {
                $ym = $monthCursor->format('Y-m');

                if ($i >= self::PROJECTION_CAP_MONTHS) {
                    // Everything from this month on rolls into the Later bucket.
                    $capped = true;
                    $laterCents = 0;
                    $laterCount = 0;
                    foreach ($months as $m) {
                        if ($m >= $ym) {
                            $laterCents += $centsByMonth[$m] ?? 0;
                            $laterCount += $countsByMonth[$m] ?? 0;
                        }
                    }
                    $buckets[] = [
                        'label' => 'Later (beyond ' . self::PROJECTION_CAP_MONTHS . ' months)',
                        'type' => 'later',
                        'count' => $laterCount,
                        'amount' => round($laterCents / 100, 2),
                    ];
                    break;
                }

                $buckets[] = [
                    'label' => $monthCursor->translatedFormat('M Y'),
                    'type' => 'month',
                    'count' => $countsByMonth[$ym] ?? 0,
                    'amount' => round(($centsByMonth[$ym] ?? 0) / 100, 2),
                ];
            }
        }

        // Contract money with no parcela date and no contract end date: it
        // has to be shown somewhere, or the buckets would not add up.
        if ($contracts['undated'] > 0 || $contracts['undated_count'] > 0) {
            $buckets[] = [
                'label' => 'No due date',
                'type' => 'undated',
                'count' => $contracts['undated_count'],
                'amount' => $contracts['undated'],
            ];
        }

        return [
            'buckets' => $buckets,
            'capped' => $capped,
            'total_open' => round($expenses['open'] + $contracts['open'], 2),
            'total_count' => $expenses['open_count'] + $contracts['open_count'],
        ];
    }
}
