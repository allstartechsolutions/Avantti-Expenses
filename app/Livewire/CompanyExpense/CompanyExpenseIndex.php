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

    /**
     * How it was paid (client request 25, Nov 2026): a method, 'autopay' for
     * expenses on automatic payment whatever the method, or 'none' for no method.
     */
    #[Url(as: 'method', except: '')]
    public string $methodFilter = '';

    /** The methods the filter and the report know, in the form's order. */
    public const METHODS = ['check', 'credit_card', 'debit_card', 'bank_transfer', 'cash', 'pix', 'other'];

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
            || $this->statusFilter !== '' || $this->methodFilter !== '' || $this->fromDate !== '' || $this->toDate !== '';
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'categoryFilter', 'vendorFilter', 'statusFilter', 'methodFilter', 'fromDate', 'toDate']);
    }

    /** The bucket an expense falls in on the payment-method report. */
    public static function methodKey(Expense $expense): string
    {
        if ($expense->is_auto_payment) {
            return 'autopay';
        }

        return $expense->payment_method ?: 'none';
    }

    public static function methodLabel(string $key): string
    {
        return match ($key) {
            'autopay' => __('Autopay'),
            'none' => __('No method'),
            default => Expense::paymentMethodLabel($key) ?? $key,
        };
    }

    /**
     * The filtered expenses by how they were paid: count, total, paid and
     * pending, largest first — the payment-method report.
     */
    protected function methodSummary($expenses): array
    {
        return $expenses->groupBy(fn (Expense $e) => self::methodKey($e))
            ->map(fn ($set, $key) => [
                'key' => $key,
                'label' => self::methodLabel($key),
                'count' => $set->count(),
                'total' => round($set->sum('total_amount'), 2),
                'paid' => round($set->sum(fn (Expense $e) => $e->getPaidAmount()), 2),
                'pending' => round($set->sum(fn (Expense $e) => $e->getPendingAmount()), 2),
            ])
            ->sortByDesc('total')
            ->values()
            ->all();
    }

    /** The list as a CSV: what the filters show, with the method summary underneath. */
    public function exportCsv()
    {
        $this->authorizeAbility('company-expenses.view');
        $this->authorizeAbility('reports.export');

        $expenses = $this->filteredQuery()->get();
        $money = fn ($v) => number_format((float) $v, 2, '.', '');

        $filename = 'company-expenses-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($expenses, $money) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [__('Date'), __('Category'), __('Vendor'), __('Items'), __('Notes'), __('Payment Method'), __('Check number'), __('Status'), __('Paid Date'), __('Total'), __('Paid'), __('Pending')]);

            foreach ($expenses as $e) {
                fputcsv($out, [
                    $e->expense_date?->format('Y-m-d'),
                    $e->category ? trim(($e->category->account_code ?? '').' '.__($e->category->name)) : '',
                    $e->supplier?->name ?? '',
                    $e->items->pluck('item_name')->implode(', ') ?: ($e->item_name ?? ''),
                    $e->notes ?? '',
                    self::methodLabel(self::methodKey($e)),
                    $e->check_number ?? '',
                    $e->getStatusLabel(),
                    $e->paid_date?->format('Y-m-d') ?? '',
                    $money($e->total_amount),
                    $money($e->getPaidAmount()),
                    $money($e->getPendingAmount()),
                ]);
            }

            fputcsv($out, []);
            fputcsv($out, [__('By payment method'), __('Expenses'), __('Total'), __('Paid'), __('Pending')]);
            foreach ($this->methodSummary($expenses) as $row) {
                fputcsv($out, [$row['label'], $row['count'], $money($row['total']), $money($row['paid']), $money($row['pending'])]);
            }
            fputcsv($out, [__('Total'), $expenses->count(), $money($expenses->sum('total_amount'))]);

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Every filter on the screen; the list, its totals, the report and the CSV read the same set. */
    protected function filteredQuery()
    {
        return Expense::company()
            ->visibleTo(Auth::user())
            ->with(['category', 'supplier', 'createdBy', 'payments', 'items', 'equipment:id,name,asset_tag,plate'])
            ->withCount('attachments')
            ->when($this->categoryFilter !== '', fn ($q) => $q->where('expense_category_id', $this->categoryFilter))
            ->when($this->vendorFilter !== '', fn ($q) => $q->where('supplier_id', $this->vendorFilter))
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->fromDate !== '', fn ($q) => $q->whereDate('expense_date', '>=', $this->fromDate))
            ->when($this->toDate !== '', fn ($q) => $q->whereDate('expense_date', '<=', $this->toDate))
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(function ($query) use ($term) {
                    $query->where('notes', 'like', $term)
                        ->orWhere('check_number', 'like', $term)
                        ->orWhereHas('items', fn ($i) => $i->where('item_name', 'like', $term))
                        ->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', $term))
                        ->orWhereHas('category', fn ($c) => $c->where('name', 'like', $term)->orWhere('account_code', 'like', $term))
                        ->orWhereHas('equipment', fn ($e) => $e->where('name', 'like', $term)->orWhere('asset_tag', 'like', $term)->orWhere('plate', 'like', $term));
                });
            })
            ->when($this->methodFilter === 'autopay', fn ($q) => $q->where('is_auto_payment', true))
            ->when($this->methodFilter === 'none', fn ($q) => $q->where('is_auto_payment', false)->whereNull('payment_method'))
            ->when(in_array($this->methodFilter, self::METHODS, true), fn ($q) => $q->where('is_auto_payment', false)->where('payment_method', $this->methodFilter))
            ->orderByDesc('expense_date')
            ->orderByDesc('id');
    }

    public function render()
    {
        $expenses = $this->filteredQuery()->get();

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
            'methodSummary' => $this->methodSummary($expenses),
        ])->layout('components.layouts.app');
    }
}
