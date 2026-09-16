<?php

namespace App\Livewire\CompanyExpense;

use App\Livewire\Concerns\AuthorizesAbility;
use App\Livewire\Concerns\HandlesExpensePaymentActions;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\ExpensePayment;
use App\Models\Vendor;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Company (general) expenses — the ones that belong to no project: rent,
 * utilities, insurance, fuel, office. Filed under a category with an
 * account code rather than a cost code, and answering to the
 * `company-expenses` area rather than `expenses` (docs/company-expenses.md).
 *
 * The screen is the project expenses list with a different scope: the same
 * view modal and the same payment actions, through
 * HandlesExpensePaymentActions, over `Expense::company()`.
 */
class CompanyExpenseIndex extends Component
{
    use AuthorizesAbility;
    use HandlesExpensePaymentActions;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $categoryFilter = '';

    #[Url(except: '')]
    public string $vendorFilter = '';

    #[Url(except: '')]
    public string $statusFilter = '';

    #[Url(except: '')]
    public string $fromDate = '';

    #[Url(except: '')]
    public string $toDate = '';

    public function mount(): void
    {
        $this->authorizeAbility('company-expenses.view');

        // The current year unless the address already says otherwise — an
        // unbounded list of every rent payment ever is nobody's first ask.
        if ($this->fromDate === '' && $this->toDate === '') {
            $this->fromDate = now()->startOfYear()->format('Y-m-d');
        }
    }

    /** Every expense on this screen is a company expense; a project id is a 404 here. */
    protected function expenseInScope(int $expenseId): Expense
    {
        return Expense::company()->findOrFail($expenseId);
    }

    protected function paymentInScope(int $paymentId): ExpensePayment
    {
        return ExpensePayment::whereHas('expense', fn ($q) => $q->company())->findOrFail($paymentId);
    }

    public function hasFilters(): bool
    {
        return $this->search !== '' || $this->categoryFilter !== '' || $this->vendorFilter !== ''
            || $this->statusFilter !== '' || $this->fromDate !== '' || $this->toDate !== '';
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'categoryFilter', 'vendorFilter', 'statusFilter', 'fromDate', 'toDate']);
    }

    public function render()
    {
        $query = Expense::company()
            ->visibleTo(Auth::user())
            ->with(['category', 'supplier', 'createdBy', 'payments', 'items'])
            ->when($this->categoryFilter !== '', fn ($q) => $q->where('expense_category_id', $this->categoryFilter))
            ->when($this->vendorFilter !== '', fn ($q) => $q->where('supplier_id', $this->vendorFilter))
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->fromDate !== '', fn ($q) => $q->whereDate('expense_date', '>=', $this->fromDate))
            ->when($this->toDate !== '', fn ($q) => $q->whereDate('expense_date', '<=', $this->toDate))
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(function ($query) use ($term) {
                    $query->where('notes', 'like', $term)
                        ->orWhereHas('items', fn ($i) => $i->where('item_name', 'like', $term))
                        ->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', $term))
                        ->orWhereHas('category', fn ($c) => $c->where('name', 'like', $term)->orWhere('account_code', 'like', $term));
                });
            })
            ->orderByDesc('expense_date')
            ->orderByDesc('id');

        $expenses = $query->get();

        $totalAmount = $expenses->sum('total_amount');
        $paidAmount = $expenses->sum(fn (Expense $e) => $e->getPaidAmount());
        $pendingAmount = $expenses->sum(fn (Expense $e) => $e->getPendingAmount());
        $overdueAmount = $expenses
            ->filter(fn (Expense $e) => $e->status === 'overdue' || $e->hasOverduePayments())
            ->sum(fn (Expense $e) => $e->getPendingAmount());

        // Retired categories stay on the filter while something is filed under them.
        $categories = ExpenseCategory::query()
            ->where(fn ($q) => $q->where('is_active', true)->orWhereHas('expenses'))
            ->ordered()
            ->get();

        $vendors = Vendor::query()
            ->where('is_supplier', true)
            ->whereHas('expenses', fn ($q) => $q->whereNull('project_id'))
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('livewire.company-expense.company-expense-index', [
            'expenses' => $expenses,
            'categories' => $categories,
            'vendors' => $vendors,
            'totalAmount' => $totalAmount,
            'paidAmount' => $paidAmount,
            'pendingAmount' => $pendingAmount,
            'overdueAmount' => $overdueAmount,
        ])->layout('components.layouts.app');
    }
}
