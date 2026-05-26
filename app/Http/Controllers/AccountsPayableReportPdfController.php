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

        $statuses = $this->statusFilterValues($statusFilter);

        $start = Carbon::parse($fromDate)->startOfDay();
        $end = Carbon::parse($toDate)->endOfDay();

        $rows = $this->buildRows($start, $end, $statuses, $projectFilter);

        $totalDue = $rows->where('status', '!=', 'paid')->sum('amount');
        $totalPaid = $rows->where('status', 'paid')->sum('amount');

        $overdueInstallments = ExpensePayment::where('status', 'overdue')
            ->whereHas('expense', function ($q) use ($projectFilter) {
                $q->where('status', '!=', 'cancelled');
                if ($projectFilter) {
                    $q->where('project_id', $projectFilter);
                }
            })
            ->sum('amount');
        $overdueOneTime = Expense::where('total_installments', 1)
            ->where('status', 'overdue')
            ->when($projectFilter, fn ($q) => $q->where('project_id', $projectFilter))
            ->get()
            ->sum('total_amount');

        $kpis = [
            'total_due' => round($totalDue, 2),
            'count_due' => $rows->where('status', '!=', 'paid')->count(),
            'total_paid' => round($totalPaid, 2),
            'overdue_total' => round(($overdueInstallments / 100) + $overdueOneTime, 2),
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

    private function statusFilterValues(string $statusFilter): array
    {
        return match ($statusFilter) {
            'pending' => ['pending'],
            'overdue' => ['overdue'],
            'paid' => ['paid'],
            'unpaid' => ['pending', 'overdue'],
            default => ['pending', 'overdue', 'paid'],
        };
    }

    private function buildRows(Carbon $start, Carbon $end, array $statuses, ?string $projectFilter): Collection
    {
        $installments = ExpensePayment::query()
            ->with(['expense.project:id,project_name', 'expense.jobSite:id,job_site_name', 'expense.supplier:id,name'])
            ->whereIn('status', $statuses)
            ->whereBetween('due_date', [$start->toDateString(), $end->toDateString()])
            ->whereHas('expense', function ($q) use ($projectFilter) {
                $q->where('status', '!=', 'cancelled');
                if ($projectFilter) {
                    $q->where('project_id', $projectFilter);
                }
            })
            ->orderBy('due_date')
            ->get()
            ->map(function (ExpensePayment $p) {
                $e = $p->expense;
                return [
                    'due_date' => $p->due_date,
                    'vendor' => $e?->supplier?->name,
                    'item' => $e?->item_name . ' (#' . $p->payment_number . ')',
                    'project' => $e?->project?->project_name,
                    'job_site' => $e?->jobSite?->job_site_name,
                    'status' => $p->status,
                    'amount' => (float) $p->amount,
                ];
            });

        $expenseStatuses = array_values(array_unique(array_map(fn ($s) => $s === 'pending' ? 'unpaid' : $s, $statuses)));

        $oneTime = Expense::query()
            ->with(['project:id,project_name', 'jobSite:id,job_site_name', 'supplier:id,name'])
            ->where('total_installments', 1)
            ->where('status', '!=', 'cancelled')
            ->whereIn('status', $expenseStatuses)
            ->whereBetween('payment_due_date', [$start->toDateString(), $end->toDateString()])
            ->when($projectFilter, fn ($q) => $q->where('project_id', $projectFilter))
            ->orderBy('payment_due_date')
            ->get()
            ->map(function (Expense $e) {
                return [
                    'due_date' => $e->payment_due_date,
                    'vendor' => $e->supplier?->name,
                    'item' => $e->item_name,
                    'project' => $e->project?->project_name,
                    'job_site' => $e->jobSite?->job_site_name,
                    'status' => $e->status === 'unpaid' ? 'pending' : $e->status,
                    'amount' => (float) $e->total_amount,
                ];
            });

        return $installments->concat($oneTime)->sortBy('due_date')->values();
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
