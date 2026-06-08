<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Expense;
use App\Models\ExpensePayment;
use App\Models\Project;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class AccountsPayableReportPdfController extends Controller
{
    public function download(Request $request)
    {
        $data = $this->buildPdfData($request);
        $pdf = Pdf::loadView('pdf.accounts-payable-report', $data);
        $pdf->setPaper('letter', 'portrait');

        $filename = 'accounts-payable-' . $data['fromDate'] . '-to-' . $data['toDate'] . '.pdf';

        return $pdf->download($filename);
    }

    public function stream(Request $request)
    {
        $data = $this->buildPdfData($request);
        $pdf = Pdf::loadView('pdf.accounts-payable-report', $data);
        $pdf->setPaper('letter', 'portrait');

        return $pdf->stream('accounts-payable.pdf');
    }

    private function buildPdfData(Request $request): array
    {
        $fromDate = $request->query('fromDate') ?: Carbon::now()->startOfMonth()->toDateString();
        $toDate = $request->query('toDate') ?: Carbon::now()->endOfMonth()->toDateString();
        $projectFilter = $request->query('projectFilter') ?: '';
        $statusFilter = $request->query('statusFilter') ?: 'unpaid';

        $start = Carbon::parse($fromDate)->startOfDay();
        $end = Carbon::parse($toDate)->endOfDay();
        $today = Carbon::now()->startOfDay();

        $rows = $this->buildRows($start, $end, $statusFilter, $projectFilter, $today);

        $from = $start->toDateString();
        $to = $end->toDateString();
        $todayStr = $today->toDateString();

        // Due in period — open items, by due date.
        $dueInstSum = $this->openInstallments($projectFilter)->whereBetween('due_date', [$from, $to])->sum('amount');
        $dueInstCount = $this->openInstallments($projectFilter)->whereBetween('due_date', [$from, $to])->count();
        $dueOne = $this->openOneTime($projectFilter)
            ->whereRaw('COALESCE(payment_due_date, expense_date) BETWEEN ? AND ?', [$from, $to])
            ->get();

        // Paid in period — paid items, by paid date.
        $paidInstSum = $this->paidInstallments($projectFilter)
            ->whereRaw('COALESCE(paid_date, due_date) BETWEEN ? AND ?', [$from, $to])
            ->sum('amount');
        $paidOne = $this->paidOneTime($projectFilter)
            ->whereRaw('COALESCE(paid_date, expense_date) BETWEEN ? AND ?', [$from, $to])
            ->get();

        // Overdue as of today — open items past due, regardless of period.
        $overdueInstSum = $this->openInstallments($projectFilter)->whereDate('due_date', '<', $todayStr)->sum('amount');
        $overdueOne = $this->openOneTime($projectFilter)
            ->whereRaw('COALESCE(payment_due_date, expense_date) < ?', [$todayStr])
            ->get();

        $kpis = [
            'total_due' => round(($dueInstSum / 100) + $dueOne->sum('total_amount'), 2),
            'count_due' => $dueInstCount + $dueOne->count(),
            'total_paid' => round(($paidInstSum / 100) + $paidOne->sum('total_amount'), 2),
            'overdue_total' => round(($overdueInstSum / 100) + $overdueOne->sum('total_amount'), 2),
        ];

        $projections = $this->buildProjections($end, $projectFilter);

        $project = $projectFilter ? Project::find($projectFilter) : null;

        return [
            'rows' => $rows,
            'kpis' => $kpis,
            'projections' => $projections,
            'fromDate' => $fromDate,
            'toDate' => $toDate,
            'statusFilter' => $statusFilter,
            'project' => $project,
            'company' => Company::first(),
            'generatedAt' => now(),
        ];
    }

    // Open = not yet paid (parent not cancelled). Overdue is derived from the
    // due date vs. today, not from a stored 'overdue' status.

    private function openInstallments(?string $projectFilter)
    {
        return ExpensePayment::query()
            ->where('status', '!=', 'paid')
            ->whereHas('expense', function ($q) use ($projectFilter) {
                $q->where('status', '!=', 'cancelled');
                if ($projectFilter) {
                    $q->where('project_id', $projectFilter);
                }
            });
    }

    private function paidInstallments(?string $projectFilter)
    {
        return ExpensePayment::query()
            ->where('status', 'paid')
            ->whereHas('expense', function ($q) use ($projectFilter) {
                $q->where('status', '!=', 'cancelled');
                if ($projectFilter) {
                    $q->where('project_id', $projectFilter);
                }
            });
    }

    private function openOneTime(?string $projectFilter)
    {
        return Expense::query()
            ->where('total_installments', 1)
            ->whereNotIn('status', ['paid', 'cancelled'])
            ->when($projectFilter, fn ($q) => $q->where('project_id', $projectFilter));
    }

    private function paidOneTime(?string $projectFilter)
    {
        return Expense::query()
            ->where('total_installments', 1)
            ->where('status', 'paid')
            ->when($projectFilter, fn ($q) => $q->where('project_id', $projectFilter));
    }

    private function mapInstallmentRow(ExpensePayment $p, Carbon $today): array
    {
        $e = $p->expense;
        $status = $p->status === 'paid'
            ? 'paid'
            : ($p->due_date && $p->due_date->lt($today) ? 'overdue' : 'pending');

        return [
            'due_date' => $p->due_date,
            'vendor' => $e?->supplier?->name,
            'item' => $e?->item_name . ' (#' . $p->payment_number . ')',
            'project' => $e?->project?->project_name,
            'job_site' => $e?->jobSite?->job_site_name,
            'status' => $status,
            'amount' => (float) $p->amount,
        ];
    }

    private function mapOneTimeRow(Expense $e, Carbon $today): array
    {
        $due = $e->payment_due_date ?? $e->expense_date;
        $status = $e->status === 'paid'
            ? 'paid'
            : ($due && $due->lt($today) ? 'overdue' : 'pending');

        return [
            'due_date' => $due,
            'vendor' => $e->supplier?->name,
            'item' => $e->item_name,
            'project' => $e->project?->project_name,
            'job_site' => $e->jobSite?->job_site_name,
            'status' => $status,
            'amount' => (float) $e->total_amount,
        ];
    }

    private function buildRows(Carbon $start, Carbon $end, string $statusFilter, ?string $projectFilter, Carbon $today): Collection
    {
        $from = $start->toDateString();
        $to = $end->toDateString();

        $wantPaid = in_array($statusFilter, ['paid', 'all'], true);
        $wantOpen = in_array($statusFilter, ['unpaid', 'pending', 'overdue', 'all'], true);

        $rows = collect();

        $instWith = ['expense.project:id,project_name', 'expense.jobSite:id,job_site_name', 'expense.supplier:id,name'];
        $oneWith = ['project:id,project_name', 'jobSite:id,job_site_name', 'supplier:id,name'];

        if ($wantOpen) {
            $openInst = $this->openInstallments($projectFilter)
                ->with($instWith)
                ->whereBetween('due_date', [$from, $to])
                ->orderBy('due_date')
                ->get()
                ->map(fn (ExpensePayment $p) => $this->mapInstallmentRow($p, $today));

            $openOne = $this->openOneTime($projectFilter)
                ->with($oneWith)
                ->whereRaw('COALESCE(payment_due_date, expense_date) BETWEEN ? AND ?', [$from, $to])
                ->orderByRaw('COALESCE(payment_due_date, expense_date)')
                ->get()
                ->map(fn (Expense $e) => $this->mapOneTimeRow($e, $today));

            $rows = $rows->concat($openInst)->concat($openOne);

            if ($statusFilter === 'pending') {
                $rows = $rows->where('status', 'pending');
            } elseif ($statusFilter === 'overdue') {
                $rows = $rows->where('status', 'overdue');
            }
        }

        if ($wantPaid) {
            $paidInst = $this->paidInstallments($projectFilter)
                ->with($instWith)
                ->whereRaw('COALESCE(paid_date, due_date) BETWEEN ? AND ?', [$from, $to])
                ->orderByRaw('COALESCE(paid_date, due_date)')
                ->get()
                ->map(fn (ExpensePayment $p) => $this->mapInstallmentRow($p, $today));

            $paidOne = $this->paidOneTime($projectFilter)
                ->with($oneWith)
                ->whereRaw('COALESCE(paid_date, expense_date) BETWEEN ? AND ?', [$from, $to])
                ->orderByRaw('COALESCE(paid_date, expense_date)')
                ->get()
                ->map(fn (Expense $e) => $this->mapOneTimeRow($e, $today));

            $rows = $rows->concat($paidInst)->concat($paidOne);
        }

        return $rows->sortBy('due_date')->values();
    }

    private function buildProjections(Carbon $toDate, ?string $projectFilter): array
    {
        $start = (clone $toDate)->endOfMonth()->addDay()->startOfMonth();
        $months = [];

        for ($i = 0; $i < 12; $i++) {
            $monthStart = (clone $start)->addMonths($i);
            $monthEnd = (clone $monthStart)->endOfMonth();

            $installmentSum = ExpensePayment::whereIn('status', ['pending', 'overdue'])
                ->whereBetween('due_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
                ->whereHas('expense', function ($q) use ($projectFilter) {
                    $q->where('status', '!=', 'cancelled');
                    if ($projectFilter) {
                        $q->where('project_id', $projectFilter);
                    }
                })
                ->sum('amount');
            $installmentCount = ExpensePayment::whereIn('status', ['pending', 'overdue'])
                ->whereBetween('due_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
                ->whereHas('expense', function ($q) use ($projectFilter) {
                    $q->where('status', '!=', 'cancelled');
                    if ($projectFilter) {
                        $q->where('project_id', $projectFilter);
                    }
                })
                ->count();

            $oneTimeRows = Expense::where('total_installments', 1)
                ->whereIn('status', ['unpaid', 'overdue'])
                ->whereBetween('payment_due_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
                ->when($projectFilter, fn ($q) => $q->where('project_id', $projectFilter))
                ->get();

            $months[] = [
                'month' => $monthStart->translatedFormat('M Y'),
                'count' => $installmentCount + $oneTimeRows->count(),
                'amount' => round(($installmentSum / 100) + $oneTimeRows->sum('total_amount'), 2),
            ];
        }

        return $months;
    }
}
