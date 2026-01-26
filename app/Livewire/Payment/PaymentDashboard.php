<?php

namespace App\Livewire\Payment;

use App\Models\Expense;
use App\Models\ExpensePayment;
use App\Models\Project;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class PaymentDashboard extends Component
{
    use WithPagination;

    // Filters
    public $projectFilter = '';
    public $statusFilter = 'all';
    public $dateFrom = '';
    public $dateTo = '';
    public $viewMode = 'upcoming'; // upcoming, overdue, all

    // For quick pay modal
    public $payingPaymentId = null;
    public $payingPaymentType = null; // 'installment' or 'one_time'
    public $paymentMethod = null;
    public $paidDate = '';

    protected $queryString = [
        'projectFilter' => ['except' => ''],
        'statusFilter' => ['except' => 'all'],
        'viewMode' => ['except' => 'upcoming'],
    ];

    public function mount()
    {
        $this->paidDate = now()->format('Y-m-d');
        $this->dateFrom = now()->startOfMonth()->format('Y-m-d');
        $this->dateTo = now()->endOfMonth()->format('Y-m-d');
    }

    public function updatingProjectFilter()
    {
        $this->resetPage();
    }

    public function updatingStatusFilter()
    {
        $this->resetPage();
    }

    public function updatingViewMode()
    {
        $this->resetPage();
    }

    public function getSummaryProperty()
    {
        $today = now()->startOfDay();
        $endOfMonth = now()->endOfMonth();

        // Pending installment payments
        $pendingInstallments = ExpensePayment::where('status', 'pending')
            ->whereHas('expense', function ($q) {
                $q->whereNotIn('status', ['cancelled']);
                if ($this->projectFilter) {
                    $q->where('project_id', $this->projectFilter);
                }
            })
            ->sum('amount');

        // Pending one-time expenses (unpaid)
        $pendingOneTime = Expense::where('status', 'unpaid')
            ->where('total_installments', 1)
            ->when($this->projectFilter, fn($q) => $q->where('project_id', $this->projectFilter))
            ->sum(DB::raw('quantity * unit_price'));

        // Overdue installment payments
        $overdueInstallments = ExpensePayment::where('status', 'overdue')
            ->whereHas('expense', function ($q) {
                $q->whereNotIn('status', ['cancelled']);
                if ($this->projectFilter) {
                    $q->where('project_id', $this->projectFilter);
                }
            })
            ->sum('amount');

        // Overdue one-time expenses
        $overdueOneTime = Expense::where('status', 'overdue')
            ->where('total_installments', 1)
            ->when($this->projectFilter, fn($q) => $q->where('project_id', $this->projectFilter))
            ->sum(DB::raw('quantity * unit_price'));

        // Due this month (installments)
        $thisMonthInstallments = ExpensePayment::where('status', 'pending')
            ->whereBetween('due_date', [$today, $endOfMonth])
            ->whereHas('expense', function ($q) {
                $q->whereNotIn('status', ['cancelled']);
                if ($this->projectFilter) {
                    $q->where('project_id', $this->projectFilter);
                }
            })
            ->sum('amount');

        // Due this month (one-time)
        $thisMonthOneTime = Expense::where('status', 'unpaid')
            ->where('total_installments', 1)
            ->whereBetween('payment_due_date', [$today, $endOfMonth])
            ->when($this->projectFilter, fn($q) => $q->where('project_id', $this->projectFilter))
            ->sum(DB::raw('quantity * unit_price'));

        // Paid this month (installments)
        $paidInstallments = ExpensePayment::where('status', 'paid')
            ->whereBetween('paid_date', [now()->startOfMonth(), $endOfMonth])
            ->whereHas('expense', function ($q) {
                if ($this->projectFilter) {
                    $q->where('project_id', $this->projectFilter);
                }
            })
            ->sum('amount');

        // Paid this month (one-time)
        $paidOneTime = Expense::whereIn('status', ['paid'])
            ->where('total_installments', 1)
            ->whereBetween('paid_date', [now()->startOfMonth(), $endOfMonth])
            ->when($this->projectFilter, fn($q) => $q->where('project_id', $this->projectFilter))
            ->sum(DB::raw('quantity * unit_price'));

        return [
            'pending' => ($pendingInstallments + $pendingOneTime) / 100,
            'overdue' => ($overdueInstallments + $overdueOneTime) / 100,
            'this_month' => ($thisMonthInstallments + $thisMonthOneTime) / 100,
            'paid_this_month' => ($paidInstallments + $paidOneTime) / 100,
        ];
    }

    public function getPaymentsProperty()
    {
        $today = now()->startOfDay();

        // Build query for installment payments
        $installmentQuery = ExpensePayment::with(['expense.project', 'expense.jobSite'])
            ->whereHas('expense', function ($q) {
                $q->whereNotIn('status', ['cancelled']);
                if ($this->projectFilter) {
                    $q->where('project_id', $this->projectFilter);
                }
            });

        // Build query for one-time unpaid expenses
        $oneTimeQuery = Expense::with(['project', 'jobSite'])
            ->where('total_installments', 1)
            ->whereIn('status', ['unpaid', 'overdue'])
            ->when($this->projectFilter, fn($q) => $q->where('project_id', $this->projectFilter));

        // Apply view mode filters
        if ($this->viewMode === 'upcoming') {
            $installmentQuery->where('status', 'pending')
                ->where('due_date', '>=', $today);
            $oneTimeQuery->where('status', 'unpaid')
                ->where(function ($q) use ($today) {
                    $q->whereNull('payment_due_date')
                        ->orWhere('payment_due_date', '>=', $today);
                });
        } elseif ($this->viewMode === 'overdue') {
            $installmentQuery->whereIn('status', ['overdue', 'pending'])
                ->where('due_date', '<', $today);
            $oneTimeQuery->where(function ($q) use ($today) {
                $q->where('status', 'overdue')
                    ->orWhere(function ($q2) use ($today) {
                        $q2->where('status', 'unpaid')
                            ->whereNotNull('payment_due_date')
                            ->where('payment_due_date', '<', $today);
                    });
            });
        } else {
            // all pending
            $installmentQuery->whereIn('status', ['pending', 'overdue']);
        }

        // Apply date range filters
        if ($this->dateFrom) {
            $installmentQuery->where('due_date', '>=', $this->dateFrom);
            $oneTimeQuery->where(function ($q) {
                $q->whereNull('payment_due_date')
                    ->orWhere('payment_due_date', '>=', $this->dateFrom);
            });
        }
        if ($this->dateTo) {
            $installmentQuery->where('due_date', '<=', $this->dateTo);
            $oneTimeQuery->where(function ($q) {
                $q->whereNull('payment_due_date')
                    ->orWhere('payment_due_date', '<=', $this->dateTo);
            });
        }

        // Get installment payments
        $installments = $installmentQuery->get()->map(function ($payment) use ($today) {
            return [
                'id' => $payment->id,
                'type' => 'installment',
                'expense_id' => $payment->expense_id,
                'description' => $payment->expense->item_name,
                'project' => $payment->expense->project->project_name,
                'job_site' => $payment->expense->jobSite?->job_site_name,
                'payment_label' => $payment->getPaymentLabel(),
                'amount' => $payment->amount,
                'due_date' => $payment->due_date,
                'is_overdue' => $payment->due_date < $today && $payment->status !== 'paid',
                'status' => $payment->status,
                'payment_method' => $payment->payment_method ?? $payment->expense->payment_method,
            ];
        });

        // Get one-time expenses
        $oneTime = $oneTimeQuery->get()->map(function ($expense) use ($today) {
            $amount = $expense->quantity * $expense->unit_price;
            return [
                'id' => $expense->id,
                'type' => 'one_time',
                'expense_id' => $expense->id,
                'description' => $expense->item_name,
                'project' => $expense->project->project_name,
                'job_site' => $expense->jobSite?->job_site_name,
                'payment_label' => '1/1',
                'amount' => $amount / 100,
                'due_date' => $expense->payment_due_date,
                'is_overdue' => $expense->payment_due_date && $expense->payment_due_date < $today,
                'status' => $expense->status === 'overdue' ? 'overdue' : 'pending',
                'payment_method' => $expense->payment_method,
            ];
        });

        // Merge and sort by due date
        $merged = $installments->merge($oneTime)
            ->sortBy('due_date')
            ->values();

        return $merged;
    }

    public function getProjectsProperty()
    {
        return Project::orderBy('project_name')->get(['id', 'project_name as name']);
    }

    public function openPayModal($paymentId, $paymentType)
    {
        $this->payingPaymentId = $paymentId;
        $this->payingPaymentType = $paymentType;
        $this->paidDate = now()->format('Y-m-d');

        if ($paymentType === 'installment') {
            $payment = ExpensePayment::find($paymentId);
            $this->paymentMethod = $payment->payment_method ?? $payment->expense->payment_method;
        } else {
            $expense = Expense::find($paymentId);
            $this->paymentMethod = $expense->payment_method;
        }

        $this->dispatch('open-modal', 'pay-modal');
    }

    public function closePayModal()
    {
        $this->dispatch('close-modal', 'pay-modal');
        $this->payingPaymentId = null;
        $this->payingPaymentType = null;
        $this->paymentMethod = null;
    }

    public function confirmPayment()
    {
        if ($this->payingPaymentType === 'installment') {
            $payment = ExpensePayment::find($this->payingPaymentId);
            if ($payment) {
                $payment->markAsPaid(
                    $this->paymentMethod,
                    Carbon::parse($this->paidDate)
                );
            }
        } else {
            $expense = Expense::find($this->payingPaymentId);
            if ($expense) {
                $expense->markAsPaid(
                    $this->paymentMethod,
                    Carbon::parse($this->paidDate)
                );
            }
        }

        $this->dispatch('close-modal', 'pay-modal');
        $this->payingPaymentId = null;
        $this->payingPaymentType = null;
        $this->paymentMethod = null;
        session()->flash('message', 'Payment marked as paid successfully.');
    }

    public function markAsOverdue($paymentId, $paymentType)
    {
        if ($paymentType === 'installment') {
            $payment = ExpensePayment::find($paymentId);
            if ($payment) {
                $payment->markAsOverdue();
            }
        } else {
            $expense = Expense::find($paymentId);
            if ($expense) {
                $expense->status = 'overdue';
                $expense->save();
            }
        }

        session()->flash('message', 'Payment marked as overdue.');
    }

    public function render()
    {
        return view('livewire.payment.payment-dashboard', [
            'summary' => $this->summary,
            'payments' => $this->payments,
            'projects' => $this->projects,
        ])->layout('components.layouts.app');
    }
}
