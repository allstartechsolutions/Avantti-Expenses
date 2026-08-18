<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\Expense;
use App\Models\ExpensePayment;
use App\Models\Income;
use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Company financial position: everything the company received and paid,
 * and everything it is still owed and still owes — in one place.
 *
 * MONEY IN
 *   - Income records: received ones by income_date, expected ones by their
 *     due date — so a receivable never depends on an invoice existing
 *   - Invoice payments (completed, net of refunds, by payment_date)
 *   - Open invoices: balance due, by the invoice due date (drafts excluded)
 *
 * MONEY OUT
 *   - Expenses: installments by expense_payments.due_date, one-time by
 *     COALESCE(payment_due_date, expense_date); paid ones by their paid date
 *   - Contracts: cash paid by payment_date, and what is still owed placed on
 *     the calendar by Contract::openPayableItems() — each open cronograma
 *     parcela on its own date, the rest on the contract's end date
 *
 * Overdue is always DERIVED (due date < today); no stored status is trusted.
 * Cancelled expenses and contracts are excluded everywhere.
 *
 * The same payload feeds the screen, the CSV and the PDF so the numbers can
 * never disagree between them.
 */
class CompanyFinancialService
{
    protected Carbon $today;

    protected ?Carbon $from = null;

    protected ?Carbon $to = null;

    protected ?array $cache = null;

    public function __construct(
        protected ?int $clientId = null,
        protected ?int $projectId = null,
        protected ?int $jobSiteId = null,
    ) {
        $this->today = Carbon::now()->startOfDay();
    }

    public static function forFilters(?int $clientId = null, ?int $projectId = null, ?int $jobSiteId = null): self
    {
        return new self($clientId, $projectId, $jobSiteId);
    }

    /**
     * Settled money is matched by the date it moved, open money by the date
     * it is due. Either bound may be null.
     */
    public function between(?string $from, ?string $to): self
    {
        $this->from = $from ? Carbon::parse($from)->startOfDay() : null;
        $this->to = $to ? Carbon::parse($to)->endOfDay() : null;

        if ($this->from && $this->to && $this->to->lt($this->from)) {
            [$this->from, $this->to] = [$this->to, $this->from];
        }

        $this->cache = null;

        return $this;
    }

    // =====================================================================
    // SCOPE
    // =====================================================================

    /** Scope a model that belongs to a project (and maybe a job site). */
    protected function applyScope($query, bool $hasJobSite = true): void
    {
        $query->when($this->projectId, fn ($q) => $q->where('project_id', $this->projectId))
            ->when($this->jobSiteId && $hasJobSite, fn ($q) => $q->where('job_site_id', $this->jobSiteId))
            ->when($this->clientId, fn ($q) => $q->whereHas('project', fn ($p) => $p->where('client_id', $this->clientId)));
    }

    protected function inRange(?Carbon $date): bool
    {
        if ($date === null) {
            return $this->from === null && $this->to === null;
        }

        if ($this->from && $date->lt($this->from)) {
            return false;
        }

        return ! ($this->to && $date->gt($this->to));
    }

    protected function row(array $row): array
    {
        return $row + [
            'date' => null,
            'direction' => 'out',
            'source' => '',
            'party' => null,
            'project' => null,
            'job_site' => null,
            'description' => '',
            'status' => 'open',
            'amount' => 0.0,
        ];
    }

    // =====================================================================
    // ITEMS — every line of the report, both directions
    // =====================================================================

    /**
     * @return Collection<int, array>
     */
    public function items(): Collection
    {
        return $this->build()['items'];
    }

    protected function buildIncomingItems(): array
    {
        $rows = [];

        $incomes = Income::query()
            ->with(['project:id,project_name,client_id', 'jobSite:id,job_site_name', 'distributions.jobSite:id,job_site_name'])
            ->tap(fn ($q) => $this->applyScope($q, hasJobSite: false))
            // Under a job-site scope, project-level money reaches the job site
            // through its distribution, so both routes have to be matched.
            ->when($this->jobSiteId, fn ($q) => $q->where(function ($w) {
                $w->where('job_site_id', $this->jobSiteId)
                    ->orWhereHas('distributions', fn ($d) => $d->where('job_site_id', $this->jobSiteId));
            }))
            ->get();

        foreach ($incomes as $income) {
            // Expected income is a receivable dated by its due date;
            // received income is cash dated by the day it arrived.
            $date = $income->effectiveDate();

            if (! $this->inRange($date)) {
                continue;
            }

            $amount = (float) $income->amount;
            $jobSiteName = $income->jobSite?->job_site_name;

            // A shared deposit counts here only for this job site's share.
            // The project scope still counts the income once, whole.
            if ($this->jobSiteId && $income->isProjectLevel()) {
                $share = $income->distributions->firstWhere('job_site_id', $this->jobSiteId);

                if (! $share) {
                    continue;
                }

                $amount = (float) $share->amount;
                $jobSiteName = $share->jobSite?->job_site_name;
            }

            $rows[] = $this->row([
                'date' => $date,
                'direction' => 'in',
                'source' => 'income',
                'party' => $income->project?->client?->company_name,
                'project' => $income->project?->project_name,
                'job_site' => $jobSiteName,
                'description' => $income->title ?: __('Income'),
                'status' => match (true) {
                    $income->isReceived() => 'settled',
                    $income->isOverdue() => 'overdue',
                    default => 'open',
                },
                'amount' => $amount,
            ]);
        }

        $invoices = Invoice::query()
            ->with(['client:id,company_name', 'project:id,project_name', 'jobSite:id,job_site_name', 'payments'])
            ->where('status', '!=', 'draft')
            ->when($this->clientId, fn ($q) => $q->where('client_id', $this->clientId))
            ->when($this->projectId, fn ($q) => $q->where('project_id', $this->projectId))
            ->when($this->jobSiteId, fn ($q) => $q->where('job_site_id', $this->jobSiteId))
            ->get();

        foreach ($invoices as $invoice) {
            foreach ($invoice->payments as $payment) {
                if (! in_array($payment->status, ['completed', 'partially_refunded'], true)) {
                    continue;
                }

                if (! $this->inRange($payment->payment_date)) {
                    continue;
                }

                $net = round(($payment->getRawOriginal('amount') - (int) $payment->getRawOriginal('refund_amount')) / 100, 2);

                if ($net <= 0) {
                    continue;
                }

                $rows[] = $this->row([
                    'date' => $payment->payment_date,
                    'direction' => 'in',
                    'source' => 'invoice',
                    'party' => $invoice->client?->company_name,
                    'project' => $invoice->project?->project_name,
                    'job_site' => $invoice->jobSite?->job_site_name,
                    'description' => __('Invoice').' '.$invoice->invoice_number,
                    'status' => 'settled',
                    'amount' => $net,
                ]);
            }

            $balance = $invoice->getBalanceDue();

            if ($balance > 0.009 && $this->inRange($invoice->due_date)) {
                $rows[] = $this->row([
                    'date' => $invoice->due_date,
                    'direction' => 'in',
                    'source' => 'invoice',
                    'party' => $invoice->client?->company_name,
                    'project' => $invoice->project?->project_name,
                    'job_site' => $invoice->jobSite?->job_site_name,
                    'description' => __('Invoice').' '.$invoice->invoice_number,
                    'status' => $invoice->due_date && $invoice->due_date->lt($this->today) ? 'overdue' : 'open',
                    'amount' => $balance,
                ]);
            }
        }

        return $rows;
    }

    protected function buildOutgoingItems(): array
    {
        $rows = [];
        $expenseWith = ['project:id,project_name', 'jobSite:id,job_site_name', 'supplier:id,name'];

        // Installments of multi-payment expenses.
        $installments = ExpensePayment::query()
            ->with(['expense' => fn ($q) => $q->with($expenseWith)])
            ->whereHas('expense', function ($q) {
                $q->where('status', '!=', 'cancelled');
                $this->applyScope($q);
            })
            ->get();

        foreach ($installments as $installment) {
            $expense = $installment->expense;
            $paid = $installment->status === 'paid';
            $date = $paid ? ($installment->paid_date ?? $installment->due_date) : $installment->due_date;

            if (! $this->inRange($date)) {
                continue;
            }

            $rows[] = $this->row([
                'date' => $date,
                'direction' => 'out',
                'source' => 'expense',
                'party' => $expense?->supplier?->name,
                'project' => $expense?->project?->project_name,
                'job_site' => $expense?->jobSite?->job_site_name,
                'description' => trim(($expense?->item_name ?? __('Expense')).' (#'.$installment->payment_number.')'),
                'status' => $paid
                    ? 'settled'
                    : ($installment->due_date && $installment->due_date->lt($this->today) ? 'overdue' : 'open'),
                'amount' => (float) $installment->amount,
            ]);
        }

        // One-time expenses.
        $oneTime = Expense::query()
            ->with($expenseWith)
            ->where('total_installments', 1)
            ->where('status', '!=', 'cancelled')
            ->tap(fn ($q) => $this->applyScope($q))
            ->get();

        foreach ($oneTime as $expense) {
            $paid = $expense->status === 'paid';
            $due = $expense->payment_due_date ?? $expense->expense_date;
            $date = $paid ? ($expense->paid_date ?? $expense->expense_date) : $due;

            if (! $this->inRange($date)) {
                continue;
            }

            $rows[] = $this->row([
                'date' => $date,
                'direction' => 'out',
                'source' => 'expense',
                'party' => $expense->supplier?->name,
                'project' => $expense->project?->project_name,
                'job_site' => $expense->jobSite?->job_site_name,
                'description' => $expense->item_name ?: __('Expense'),
                'status' => $paid ? 'settled' : ($due && $due->lt($this->today) ? 'overdue' : 'open'),
                'amount' => (float) $expense->total_amount,
            ]);
        }

        // Contracts: cash paid, and what is still owed with its own dates.
        $contracts = Contract::query()
            ->with([
                'project:id,project_name', 'jobSite:id,job_site_name', 'subcontractor:id,name',
                'changeOrders', 'payments', 'scheduleItems.payments', 'scheduleItems.measurements.payments',
            ])
            ->where('status', '!=', 'cancelled')
            ->tap(fn ($q) => $this->applyScope($q))
            ->get();

        foreach ($contracts as $contract) {
            foreach ($contract->payments as $payment) {
                if (! $this->inRange($payment->payment_date)) {
                    continue;
                }

                $rows[] = $this->row([
                    'date' => $payment->payment_date,
                    'direction' => 'out',
                    'source' => 'contract',
                    'party' => $contract->subcontractor?->company_name,
                    'project' => $contract->project?->project_name,
                    'job_site' => $contract->jobSite?->job_site_name,
                    'description' => __('Contract').' '.$contract->contract_number
                        .($payment->is_retention_release ? ' — '.__('Retention Release') : ''),
                    'status' => 'settled',
                    'amount' => (float) $payment->amount,
                ]);
            }

            foreach ($contract->openPayableItems() as $open) {
                if (! $this->inRange($open['date'])) {
                    continue;
                }

                $rows[] = $this->row([
                    'date' => $open['date'],
                    'direction' => 'out',
                    'source' => 'contract',
                    'party' => $contract->subcontractor?->company_name,
                    'project' => $contract->project?->project_name,
                    'job_site' => $contract->jobSite?->job_site_name,
                    'description' => __('Contract').' '.$contract->contract_number.' — '.$open['label'],
                    'status' => $open['date'] && $open['date']->lt($this->today) ? 'overdue' : 'open',
                    'amount' => (float) $open['amount'],
                ]);
            }
        }

        return $rows;
    }

    // =====================================================================
    // BUILD
    // =====================================================================

    public function build(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $items = collect(array_merge($this->buildIncomingItems(), $this->buildOutgoingItems()))
            ->sortBy([
                fn ($a, $b) => ($a['date']?->timestamp ?? PHP_INT_MAX) <=> ($b['date']?->timestamp ?? PHP_INT_MAX),
            ])
            ->values();

        return $this->cache = [
            'in' => $this->summarize($items->where('direction', 'in')),
            'out' => $this->summarize($items->where('direction', 'out')),
            'net' => $this->netPosition($items),
            'sources' => $this->bySource($items),
            'timeline' => $this->timeline($items),
            'items' => $items,
        ];
    }

    protected function summarize(Collection $rows): array
    {
        $settled = $rows->where('status', 'settled');
        $overdue = $rows->where('status', 'overdue');
        $open = $rows->whereIn('status', ['open', 'overdue']);

        return [
            'settled' => round($settled->sum('amount'), 2),
            'open' => round($open->sum('amount'), 2),
            'overdue' => round($overdue->sum('amount'), 2),
            'upcoming' => round($open->sum('amount') - $overdue->sum('amount'), 2),
            'total' => round($settled->sum('amount') + $open->sum('amount'), 2),
            'settled_count' => $settled->count(),
            'open_count' => $open->count(),
            'overdue_count' => $overdue->count(),
        ];
    }

    /**
     * Cash position is what actually moved; the forecast adds what is still
     * owed either way, so it answers "where does this end up".
     */
    protected function netPosition(Collection $items): array
    {
        $in = $this->summarize($items->where('direction', 'in'));
        $out = $this->summarize($items->where('direction', 'out'));

        return [
            'cash' => round($in['settled'] - $out['settled'], 2),
            'to_receive' => $in['open'],
            'to_pay' => $out['open'],
            'forecast' => round(($in['settled'] + $in['open']) - ($out['settled'] + $out['open']), 2),
        ];
    }

    /**
     * One row per money source: what moved, what is still open, and the
     * total each represents.
     */
    protected function bySource(Collection $items): array
    {
        $sources = [
            'income' => __('Income'),
            'invoice' => __('Invoices'),
            'expense' => __('Expenses'),
            'contract' => __('Contracts'),
        ];

        $rows = [];

        foreach ($sources as $key => $label) {
            $subset = $items->where('source', $key);

            if ($subset->isEmpty()) {
                continue;
            }

            $summary = $this->summarize($subset);
            $rows[] = [
                'key' => $key,
                'label' => $label,
                'direction' => $subset->first()['direction'],
                'settled' => $summary['settled'],
                'open' => $summary['open'],
                'overdue' => $summary['overdue'],
                'total' => $summary['total'],
            ];
        }

        return $rows;
    }

    /**
     * Month-by-month in / out / net, oldest first. Undated money (an open
     * contract balance with no end date) is reported separately so the
     * months always add up.
     */
    protected function timeline(Collection $items): array
    {
        $months = [];
        $undated = ['in' => 0.0, 'out' => 0.0];

        foreach ($items as $item) {
            if ($item['date'] === null) {
                $undated[$item['direction']] = round($undated[$item['direction']] + $item['amount'], 2);

                continue;
            }

            $key = $item['date']->format('Y-m');
            $months[$key] ??= ['month' => $key, 'label' => $item['date']->translatedFormat('M Y'), 'in' => 0.0, 'out' => 0.0];
            $months[$key][$item['direction']] = round($months[$key][$item['direction']] + $item['amount'], 2);
        }

        ksort($months);

        $rows = array_values(array_map(function (array $month) {
            $month['net'] = round($month['in'] - $month['out'], 2);

            return $month;
        }, $months));

        return [
            'months' => $rows,
            'undated' => $undated + ['net' => round($undated['in'] - $undated['out'], 2)],
        ];
    }
}
