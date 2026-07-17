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
 *   - Contracts have NO payment schedule (payments are recorded after the
 *     fact), so they appear as point-in-time totals only and are excluded
 *     from the monthly projection.
 *   - Cancelled expenses and contracts are excluded everywhere.
 *
 * The same build() payload feeds the Livewire pages and the PDF controllers
 * so screen and PDF numbers always match.
 */
class PaymentScheduleService
{
    public const PROJECTION_CAP_MONTHS = 24;

    protected Carbon $today;

    protected ?array $expenseScheduleCache = null;

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
            });
    }

    protected function paidInstallments()
    {
        return ExpensePayment::query()
            ->where('status', 'paid')
            ->whereHas('expense', function ($q) {
                $q->where('status', '!=', 'cancelled');
                $this->applyScope($q);
            });
    }

    protected function openOneTime()
    {
        return Expense::query()
            ->where('total_installments', 1)
            ->whereNotIn('status', ['paid', 'cancelled'])
            ->tap(fn ($q) => $this->applyScope($q));
    }

    protected function paidOneTime()
    {
        return Expense::query()
            ->where('total_installments', 1)
            ->where('status', 'paid')
            ->tap(fn ($q) => $this->applyScope($q));
    }

    protected function contracts()
    {
        return Contract::query()
            ->with(['changeOrders', 'payments'])
            ->where('status', '!=', 'cancelled')
            ->tap(fn ($q) => $this->applyScope($q));
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
     * Contract totals, point-in-time — contracts carry no payment schedule.
     */
    public function contractSummary(): array
    {
        $contracts = $this->contracts()->get();

        $adjusted = round($contracts->sum(fn (Contract $c) => $c->getAdjustedAmount()), 2);
        $paid = round($contracts->sum(fn (Contract $c) => $c->getAmountPaid()), 2);

        return [
            'adjusted' => $adjusted,
            'paid' => $paid,
            'balance' => round($adjusted - $paid, 2),
            'count' => $contracts->count(),
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
     * Monthly projection of open expense payments, by due date:
     * an Overdue bucket, one row per month until the last scheduled open
     * payment (capped), and a Later bucket when the cap cuts the tail off.
     *
     * The current month's row covers [today, end of month] so items already
     * past due this month land only in the Overdue bucket.
     */
    public function projection(): array
    {
        $today = $this->today->toDateString();
        $buckets = [];

        $expenses = $this->expenseSchedule();

        if ($expenses['overdue'] > 0 || $expenses['overdue_count'] > 0) {
            $buckets[] = [
                'label' => 'Overdue (past due)',
                'type' => 'overdue',
                'count' => $expenses['overdue_count'],
                'amount' => $expenses['overdue'],
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

        $months = collect(array_keys($centsByMonth))->sort();
        $capped = false;

        if ($months->isNotEmpty()) {
            $monthCursor = $this->today->copy()->startOfMonth();
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

        return [
            'buckets' => $buckets,
            'capped' => $capped,
            'total_open' => $expenses['open'],
            'total_count' => $expenses['open_count'],
        ];
    }
}
