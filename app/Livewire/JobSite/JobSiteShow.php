<?php

namespace App\Livewire\JobSite;

use App\Livewire\Concerns\AuthorizesAbility;
use App\Livewire\Concerns\ManagesChangeOrders;
use App\Services\CostCodeLedger;
use App\Models\CatalogItem;
use App\Models\ChangeOrder;
use App\Models\DailyReport;
use App\Models\DailyReportImage;
use App\Models\DailyReportManpower;
use App\Models\DailyReportTask;
use App\Models\Expense;
use App\Models\JobSite;
use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

class JobSiteShow extends Component
{
    use WithFileUploads, AuthorizesAbility, ManagesChangeOrders;

    public JobSite $jobSite;
    public $activeTab = 'overview';

    // Delete Job Site modal
    public $showDeleteJobSiteModal = false;
    public $deleteJobSiteData = [];

    // Expense properties
    public $expenseSearch = '';
    public $showExpenseModal = false;
    public $expenseModalMode = 'create';
    public $editingExpense = null;
    public $expenseHistory = [];

    // Mark-as-paid confirmation state (inline date picker)
    public $markPaidType = null; // 'expense' or 'payment'
    public $markPaidId = null;
    public $markPaidDate = '';

    // Installment due-date editing state
    public $editDueDateId = null;
    public $editDueDate = '';

    // Expense form properties
    public $catalogItemSearch = '';
    public $selectedCatalogItem = null;
    public $isCustomItem = false;
    public $expense_item_name = '';
    public $expense_item_type = '';
    public $expense_purchase_unit = '';
    public $expense_usage_unit = '';
    public $expense_unit_type_used = 'custom'; // 'purchase', 'usage', or 'custom'
    public $expense_quantity = '';
    public $expense_unit_price = '';
    public $expense_total_amount = '';
    public $expense_notes = '';
    public $expense_date = '';
    public $expense_receipt = null;
    public $existingReceiptPath = null;

    // Expense payment properties
    public $expense_status = 'paid';
    public $expense_payment_method = null;
    public $expense_is_auto_payment = false;
    public $expense_has_installments = false;
    public $expense_total_installments = 2;
    public $expense_payment_frequency = 'monthly';
    public $expense_payment_due_date = '';
    public $expense_paid_date = '';
    public $expense_use_custom_amounts = false;
    public $expense_custom_amounts = [];
    public $expense_payment_schedule_preview = [];
    public $expenseStatusFilter = 'all';

    /** Narrow the expense list to one cost code. */
    public $expenseCostCodeFilter = 'all';

    public $activeNavTab = 'overview';

    public function mount(JobSite $jobSite)
    {
        $this->jobSite = $jobSite->load('project');

        // Detect which section page we're on from the URL
        $path = request()->path();
        $tabMap = [
            'expenses' => 'expenses',
            'change-orders' => 'changeorders',
            'purchase-orders' => 'purchaseorders',
            'daily-reports' => 'dailyreports',
            'budget' => 'budget',
        ];

        foreach ($tabMap as $segment => $tab) {
            if (str_ends_with($path, '/' . $segment)) {
                $this->activeTab = $tab;
                $this->activeNavTab = $segment;
                break;
            }
        }

        $this->authorizeTab($this->activeTab);

        // "Create change order" on an RFI arrives here carrying ?fromRfi=.
        if ($this->activeTab === 'changeorders') {
            $this->applyChangeOrderQueryIntent();
        }
    }

    public function setActiveTab($tab)
    {
        $this->authorizeTab($tab);

        $this->activeTab = $tab;
    }

    /**
     * This one component serves five job-site tabs, so the guard belongs to the
     * tab rather than to the route: switching tab is not a fresh request, and
     * `setActiveTab` can be called straight from the browser.
     *
     * Only the swept modules answer here. The rest keep their old rules until
     * their own pass, exactly as the legacy bridge intends.
     */
    protected function authorizeTab(string $tab): void
    {
        $abilities = [
            'expenses' => 'expenses.view',
            'purchaseorders' => 'purchase-orders.view',
            'changeorders' => 'change-orders.view',
            'dailyreports' => 'daily-reports.view',
            'budget' => 'budget.view',
        ];

        if (isset($abilities[$tab])) {
            $this->authorizeAbility($abilities[$tab], $this->jobSite);
        }
    }

    /** An expense of THIS job site, or a 404. */
    protected function expenseInScope(int $expenseId): Expense
    {
        return Expense::where('job_site_id', $this->jobSite->id)->findOrFail($expenseId);
    }

    /** An installment of an expense of THIS job site, or a 404. */
    protected function paymentInScope(int $paymentId): \App\Models\ExpensePayment
    {
        return \App\Models\ExpensePayment::whereHas(
            'expense',
            fn ($q) => $q->where('job_site_id', $this->jobSite->id)
        )->findOrFail($paymentId);
    }

    protected function changeOrderProjectId(): int
    {
        return $this->jobSite->project_id;
    }

    /** This screen only ever shows and writes change orders for its own job site. */
    protected function changeOrderPinnedJobSiteId(): int|false
    {
        return $this->jobSite->id;
    }

    protected function afterChangeOrderSaved(): void
    {
        $this->jobSite->refresh();
    }

    // Expense methods
    public function updatedCatalogItemSearch()
    {
        if (empty($this->catalogItemSearch)) {
            $this->selectedCatalogItem = null;
        }
    }

    public function selectCatalogItem($itemId)
    {
        $item = CatalogItem::find($itemId);

        if ($item) {
            $this->selectedCatalogItem = $itemId;
            $this->catalogItemSearch = $item->name;
            $this->expense_item_name = $item->name;
            $this->expense_item_type = $item->type;
            $this->expense_purchase_unit = $item->purchase_unit ?? '';
            $this->expense_usage_unit = $item->usage_unit ?? '';
            $this->isCustomItem = false;

            // For products with unit conversion, default to usage unit
            // For services/rentals, use purchase unit (or custom)
            if ($item->type === 'product' && $item->usage_unit) {
                $this->expense_unit_type_used = 'usage';
                $this->expense_unit_price = $item->unit_cost; // unit cost (price per usage unit)
            } else {
                $this->expense_unit_type_used = 'purchase';
                $this->expense_unit_price = $item->current_cost; // purchase cost
            }

            $this->calculateExpenseTotal();
        }
    }

    public function updatedExpenseUnitTypeUsed()
    {
        if (!$this->selectedCatalogItem) {
            return;
        }

        $item = CatalogItem::find($this->selectedCatalogItem);
        if ($item) {
            if ($this->expense_unit_type_used === 'purchase') {
                $this->expense_unit_price = $item->current_cost;
            } elseif ($this->expense_unit_type_used === 'usage') {
                $this->expense_unit_price = $item->unit_cost;
            }
            $this->calculateExpenseTotal();
        }
    }

    public function toggleCustomItem()
    {
        $this->isCustomItem = !$this->isCustomItem;

        if ($this->isCustomItem) {
            $this->reset(['selectedCatalogItem', 'catalogItemSearch', 'expense_item_name', 'expense_item_type', 'expense_purchase_unit', 'expense_usage_unit', 'expense_unit_type_used', 'expense_unit_price', 'expense_quantity', 'expense_total_amount']);
            $this->expense_unit_type_used = 'custom';
        } else {
            $this->reset(['expense_item_name', 'expense_item_type', 'expense_purchase_unit', 'expense_usage_unit', 'expense_unit_type_used', 'expense_unit_price', 'expense_quantity', 'expense_total_amount']);
        }
    }

    public function calculateExpenseTotal()
    {
        if ($this->expense_quantity && $this->expense_unit_price) {
            $this->expense_total_amount = number_format($this->expense_quantity * $this->expense_unit_price, 2, '.', '');
        }
    }

    public function updatedExpenseQuantity()
    {
        $this->calculateExpenseTotal();
    }

    public function updatedExpenseUnitPrice()
    {
        $this->calculateExpenseTotal();
    }

    public function openExpenseCreateModal()
    {
        $this->authorizeAbility('expenses.create', $this->jobSite);

        $this->reset([
            'catalogItemSearch', 'selectedCatalogItem', 'isCustomItem',
            'expense_item_name', 'expense_item_type', 'expense_purchase_unit', 'expense_usage_unit',
            'expense_unit_type_used', 'expense_quantity', 'expense_unit_price', 'expense_total_amount',
            'expense_notes', 'expense_date', 'expense_receipt', 'existingReceiptPath', 'editingExpense',
            'expenseHistory',
            // Payment fields
            'expense_status', 'expense_payment_method', 'expense_is_auto_payment',
            'expense_has_installments', 'expense_total_installments', 'expense_payment_frequency',
            'expense_payment_due_date', 'expense_paid_date', 'expense_use_custom_amounts',
            'expense_custom_amounts', 'expense_payment_schedule_preview',
            'markPaidType', 'markPaidId', 'markPaidDate',
            'editDueDateId', 'editDueDate'
        ]);
        $this->expense_date = now()->format('Y-m-d');
        $this->expense_paid_date = now()->format('Y-m-d');
        $this->expense_payment_due_date = now()->format('Y-m-d');
        $this->expense_unit_type_used = 'custom';
        $this->expense_status = 'paid';
        $this->expense_total_installments = 2;
        $this->expense_payment_frequency = 'monthly';
        $this->expenseModalMode = 'create';
        $this->showExpenseModal = true;
        $this->dispatch('open-modal', 'expense-modal');
    }

    public function openExpenseEditModal($expenseId)
    {
        $expense = $this->expenseInScope((int) $expenseId)->load('payments');

        $this->authorizeAbility('expenses.edit', $expense);

        // Settled money needs `expenses.edit_paid` on top of `expenses.edit`.
        if (!$expense->isEditableBy(auth()->user())) {
            session()->flash('error', __('This expense cannot be edited because it has payments.'));
            return;
        }

        $this->editingExpense = $expense->id;
        $this->isCustomItem = $expense->isCustom();

        if (!$this->isCustomItem) {
            $this->selectedCatalogItem = $expense->catalog_item_id;
            $this->catalogItemSearch = $expense->catalogItem?->name;
        }

        $this->expense_item_name = $expense->item_name;
        $this->expense_item_type = $expense->item_type;
        $this->expense_purchase_unit = $expense->purchase_unit;
        $this->expense_usage_unit = $expense->usage_unit;
        $this->expense_unit_type_used = $expense->unit_type_used;
        $this->expense_quantity = $expense->quantity;
        $this->expense_unit_price = $expense->unit_price;
        $this->expense_total_amount = $expense->total_amount;
        $this->expense_notes = $expense->notes;
        $this->expense_date = $expense->expense_date->format('Y-m-d');
        $this->existingReceiptPath = $expense->receipt_path;
        $this->expense_receipt = null;

        // Payment fields
        $this->expense_status = $expense->status;
        $this->expense_payment_method = $expense->payment_method;
        $this->expense_is_auto_payment = $expense->is_auto_payment;
        $this->expense_has_installments = $expense->isInstallment();
        $this->expense_total_installments = $expense->total_installments;
        $this->expense_payment_frequency = $expense->payment_frequency ?? 'monthly';
        $this->expense_payment_due_date = $expense->payment_due_date?->format('Y-m-d') ?? now()->format('Y-m-d');
        $this->expense_paid_date = $expense->paid_date?->format('Y-m-d') ?? now()->format('Y-m-d');

        // Load custom amounts if installments
        if ($expense->isInstallment()) {
            $this->expense_use_custom_amounts = false;
            $this->expense_custom_amounts = $expense->payments->pluck('amount')->toArray();
            $this->generatePaymentSchedulePreview();
        }

        $this->expenseModalMode = 'edit';
        $this->showExpenseModal = true;
        $this->dispatch('open-modal', 'expense-modal');
    }

    public function openExpenseViewModal($expenseId)
    {
        $expense = $this->expenseInScope((int) $expenseId)->load('payments');

        $this->authorizeAbility('expenses.view', $expense);

        $this->editingExpense = $expense->id;
        $this->isCustomItem = $expense->isCustom();

        if (!$this->isCustomItem) {
            $this->selectedCatalogItem = $expense->catalog_item_id;
            $this->catalogItemSearch = $expense->catalogItem?->name;
        }

        $this->expense_item_name = $expense->item_name;
        $this->expense_item_type = $expense->item_type;
        $this->expense_purchase_unit = $expense->purchase_unit;
        $this->expense_usage_unit = $expense->usage_unit;
        $this->expense_unit_type_used = $expense->unit_type_used;
        $this->expense_quantity = $expense->quantity;
        $this->expense_unit_price = $expense->unit_price;
        $this->expense_total_amount = $expense->total_amount;
        $this->expense_notes = $expense->notes;
        $this->expense_date = $expense->expense_date->format('Y-m-d');
        $this->existingReceiptPath = $expense->receipt_path;

        // Payment fields
        $this->expense_status = $expense->status;
        $this->expense_payment_method = $expense->payment_method;
        $this->expense_is_auto_payment = $expense->is_auto_payment;
        $this->expense_has_installments = $expense->isInstallment();
        $this->expense_total_installments = $expense->total_installments;
        $this->expense_payment_frequency = $expense->payment_frequency;
        $this->expense_payment_due_date = $expense->payment_due_date?->format('Y-m-d');
        $this->expense_paid_date = $expense->paid_date?->format('Y-m-d');

        $this->loadExpenseHistory($expense);

        $this->expenseModalMode = 'view';
        $this->showExpenseModal = true;
        $this->dispatch('open-modal', 'expense-modal');
    }

    protected function loadExpenseHistory(Expense $expense): void
    {
        $this->expenseHistory = $expense->changeHistories()
            ->with(['changedBy', 'expensePayment'])
            ->get()
            ->map(fn ($h) => [
                'label' => $h->getActionLabel(),
                'color' => $h->getActionColor(),
                'user' => $h->changedBy?->name,
                'date' => $h->created_at->format('M d, Y H:i'),
                'changes' => $h->changes,
            ])
            ->toArray();
    }

    /** Take the chosen receipt back off before the record is saved. */
    public function clearExpenseReceipt()
    {
        // Livewire's own `_removeUpload()` deletes the temporary file; dropping
        // only the reference would leave it in livewire-tmp until the daily
        // sweep.
        $this->expense_receipt?->delete();

        $this->expense_receipt = null;
    }

    public function saveExpense()
    {
        // The modal writes to this job site and nowhere else, so the ability is
        // asked about the job site — create or correct, depending on the mode.
        if ($this->expenseModalMode === 'edit' && $this->editingExpense) {
            $existing = $this->expenseInScope((int) $this->editingExpense);

            $this->authorizeAbility('expenses.edit', $existing);

            if (! $existing->isEditable()) {
                $this->authorizeAbility('expenses.edit_paid', $existing);
            }
        } else {
            $this->authorizeAbility('expenses.create', $this->jobSite);
        }

        $rules = [
            'expense_item_name' => 'required|string|max:255',
            'expense_quantity' => 'required|numeric|min:0.01',
            'expense_unit_price' => 'required|numeric|min:0',
            'expense_date' => 'required|date',
            'expense_receipt' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'expense_payment_method' => 'nullable|in:cash,check,credit_card,debit_card,bank_transfer,pix,other',
        ];

        // Add conditional validation based on payment type
        if ($this->expense_has_installments) {
            $rules['expense_total_installments'] = 'required|integer|min:2|max:120';
            $rules['expense_payment_frequency'] = 'required|in:weekly,biweekly,monthly';
            $rules['expense_payment_due_date'] = 'required|date';
        } else {
            $rules['expense_status'] = 'required|in:unpaid,paid';
            if ($this->expense_status === 'unpaid') {
                $rules['expense_payment_due_date'] = 'required|date';
            } else {
                $rules['expense_paid_date'] = 'nullable|date';
            }
        }

        $this->validate($rules);

        $receiptPath = $this->existingReceiptPath;

        if ($this->expense_receipt) {
            if ($this->existingReceiptPath) {
                Storage::delete($this->existingReceiptPath);
            }
            $receiptPath = $this->expense_receipt->store('expenses', 'local');
        }

        $data = [
            'project_id' => $this->jobSite->project_id,
            'job_site_id' => $this->jobSite->id,
            'catalog_item_id' => $this->isCustomItem ? null : $this->selectedCatalogItem,
            'item_name' => $this->expense_item_name,
            'item_type' => $this->expense_item_type,
            'purchase_unit' => $this->expense_purchase_unit,
            'usage_unit' => $this->expense_usage_unit,
            'unit_type_used' => $this->expense_unit_type_used,
            'quantity' => $this->expense_quantity,
            'unit_price' => $this->expense_unit_price,
            'total_amount' => $this->expense_total_amount,
            'notes' => $this->expense_notes,
            'receipt_path' => $receiptPath,
            'expense_date' => $this->expense_date,
            // Payment fields
            'payment_method' => $this->expense_payment_method,
            'is_auto_payment' => $this->expense_is_auto_payment,
        ];

        // Handle installments vs one-time payment
        if ($this->expense_has_installments) {
            $data['status'] = 'unpaid';
            $data['total_installments'] = $this->expense_total_installments;
            $data['payment_frequency'] = $this->expense_payment_frequency;
            $data['payment_due_date'] = $this->expense_payment_due_date;
            $data['paid_date'] = null;
        } else {
            $data['status'] = $this->expense_status;
            $data['total_installments'] = 1;
            $data['payment_frequency'] = null;

            if ($this->expense_status === 'paid') {
                $data['paid_date'] = $this->expense_paid_date ?: now()->format('Y-m-d');
                $data['payment_due_date'] = null;
            } else {
                $data['paid_date'] = null;
                $data['payment_due_date'] = $this->expense_payment_due_date;
            }
        }

        if ($this->expenseModalMode === 'edit' && $this->editingExpense) {
            $expense = $this->expenseInScope((int) $this->editingExpense);

            // Settled money needs `expenses.edit_paid` on top of `expenses.edit`.
            if (!$expense->isEditableBy(auth()->user())) {
                session()->flash('error', __('This expense cannot be edited because it has payments.'));
                return;
            }

            $expense->updateWithHistory($data);

            // Regenerate payment schedule if installments changed
            // (locked once any installment has been paid)
            if (!$expense->hasLockedPayments()) {
                if ($this->expense_has_installments) {
                    $customAmounts = $this->expense_use_custom_amounts ? $this->expense_custom_amounts : null;
                    $expense->generatePaymentSchedule($customAmounts);
                } else {
                    // Remove any existing payments if changed to one-time
                    $expense->payments()->delete();
                }
            }

            session()->flash('message', __('Expense updated successfully!'));
        } else {
            $data['created_by'] = Auth::id();
            $expense = Expense::create($data);

            // Generate payment schedule for installments
            if ($this->expense_has_installments) {
                $customAmounts = $this->expense_use_custom_amounts ? $this->expense_custom_amounts : null;
                $expense->generatePaymentSchedule($customAmounts);
            }

            session()->flash('message', __('Expense added successfully!'));
        }

        $this->closeExpenseModal();
        $this->jobSite->refresh();
    }

    public function deleteExpense($expenseId)
    {
        $expense = $this->expenseInScope((int) $expenseId);

        $this->authorizeAbility('expenses.delete', $expense);

        $expense->delete();

        session()->flash('message', __('Expense deleted successfully!'));
        $this->jobSite->refresh();
    }

    public function closeExpenseModal()
    {
        $this->showExpenseModal = false;
        $this->reset([
            'catalogItemSearch', 'selectedCatalogItem', 'isCustomItem',
            'expense_item_name', 'expense_item_type', 'expense_purchase_unit', 'expense_usage_unit',
            'expense_unit_type_used', 'expense_quantity', 'expense_unit_price', 'expense_total_amount',
            'expense_notes', 'expense_date', 'expense_receipt', 'existingReceiptPath', 'editingExpense',
            'expenseHistory',
            // Payment fields
            'expense_status', 'expense_payment_method', 'expense_is_auto_payment',
            'expense_has_installments', 'expense_total_installments', 'expense_payment_frequency',
            'expense_payment_due_date', 'expense_paid_date', 'expense_use_custom_amounts',
            'expense_custom_amounts', 'expense_payment_schedule_preview',
            'markPaidType', 'markPaidId', 'markPaidDate',
            'editDueDateId', 'editDueDate'
        ]);
        $this->dispatch('close-modal', 'expense-modal');
    }

    // Payment schedule methods
    public function updatedExpenseHasInstallments()
    {
        if ($this->expense_has_installments) {
            $this->expense_status = 'unpaid';
            $this->expense_payment_due_date = $this->expense_payment_due_date ?: now()->format('Y-m-d');
            $this->generatePaymentSchedulePreview();
        } else {
            $this->expense_status = 'paid';
            $this->expense_payment_schedule_preview = [];
        }
    }

    public function updatedExpenseTotalInstallments()
    {
        $this->generatePaymentSchedulePreview();
    }

    public function updatedExpensePaymentFrequency()
    {
        $this->generatePaymentSchedulePreview();
    }

    public function updatedExpensePaymentDueDate()
    {
        $this->generatePaymentSchedulePreview();
    }

    public function updatedExpenseTotalAmount()
    {
        $this->generatePaymentSchedulePreview();
    }

    public function updatedExpenseUseCustomAmounts()
    {
        if ($this->expense_use_custom_amounts) {
            $this->initializeCustomAmounts();
        }
        $this->generatePaymentSchedulePreview();
    }

    public function initializeCustomAmounts()
    {
        $total = floatval($this->expense_total_amount) ?: 0;
        $count = intval($this->expense_total_installments) ?: 2;

        $equalAmount = round($total / $count, 2);
        $this->expense_custom_amounts = array_fill(0, $count, $equalAmount);

        // Adjust last payment for rounding
        $sum = array_sum($this->expense_custom_amounts);
        $diff = round($total - $sum, 2);
        if ($diff != 0 && count($this->expense_custom_amounts) > 0) {
            $this->expense_custom_amounts[count($this->expense_custom_amounts) - 1] += $diff;
        }
    }

    public function updateCustomAmount($index, $value)
    {
        $this->expense_custom_amounts[$index] = floatval($value);
        $this->generatePaymentSchedulePreview();
    }

    public function generatePaymentSchedulePreview()
    {
        if (!$this->expense_has_installments || !$this->expense_total_amount || !$this->expense_total_installments) {
            $this->expense_payment_schedule_preview = [];
            return;
        }

        $total = floatval($this->expense_total_amount);
        $count = intval($this->expense_total_installments);
        $frequency = $this->expense_payment_frequency ?: 'monthly';
        $startDate = $this->expense_payment_due_date ? \Carbon\Carbon::parse($this->expense_payment_due_date) : now();

        // Calculate amounts
        if ($this->expense_use_custom_amounts && count($this->expense_custom_amounts) === $count) {
            $amounts = $this->expense_custom_amounts;
        } else {
            $equalAmount = round($total / $count, 2);
            $amounts = array_fill(0, $count, $equalAmount);

            // Adjust last payment for rounding
            $sum = array_sum($amounts);
            $diff = round($total - $sum, 2);
            if ($diff != 0) {
                $amounts[$count - 1] += $diff;
            }
        }

        $this->expense_payment_schedule_preview = [];

        for ($i = 0; $i < $count; $i++) {
            $dueDate = match ($frequency) {
                'weekly' => $startDate->copy()->addWeeks($i),
                'biweekly' => $startDate->copy()->addWeeks($i * 2),
                'monthly' => $startDate->copy()->addMonths($i),
                default => $startDate->copy()->addMonths($i),
            };

            $this->expense_payment_schedule_preview[] = [
                'number' => $i + 1,
                'due_date' => $dueDate->format('Y-m-d'),
                'due_date_formatted' => $dueDate->format('M d, Y'),
                'amount' => $amounts[$i],
            ];
        }
    }

    public function getCustomAmountsTotal()
    {
        return array_sum($this->expense_custom_amounts ?? []);
    }

    // Start marking a payment/expense as paid (shows inline date picker)
    public function startMarkPaid(string $type, $id)
    {
        $this->authorizeAbility('expenses.pay', $type === 'payment'
            ? $this->paymentInScope((int) $id)
            : $this->expenseInScope((int) $id));

        $this->markPaidType = $type;
        $this->markPaidId = $id;
        $this->markPaidDate = now()->format('Y-m-d');
    }

    public function cancelMarkPaid()
    {
        $this->reset(['markPaidType', 'markPaidId', 'markPaidDate']);
    }

    // Confirm mark as paid with the chosen date
    public function confirmMarkPaid()
    {
        $this->validate(['markPaidDate' => 'required|date']);

        $paidDate = \Carbon\Carbon::parse($this->markPaidDate);

        if ($this->markPaidType === 'payment') {
            $payment = $this->paymentInScope((int) $this->markPaidId);
            $this->authorizeAbility('expenses.pay', $payment);
            $payment->markAsPaid($this->expense_payment_method, $paidDate);
            $expenseId = $payment->expense_id;
            session()->flash('message', __('Payment marked as paid.'));
        } else {
            $expense = $this->expenseInScope((int) $this->markPaidId);
            $this->authorizeAbility('expenses.pay', $expense);
            if ($expense->isOneTime()) {
                $expense->markAsPaid(null, $paidDate);
            }
            $expenseId = $expense->id;
            session()->flash('message', __('Expense marked as paid.'));
        }

        $this->cancelMarkPaid();
        $this->jobSite->refresh();

        // Refresh the view modal if open
        if ($this->showExpenseModal && $this->expenseModalMode === 'view') {
            $this->openExpenseViewModal($expenseId);
        }
    }

    // Mark payment as overdue
    public function markPaymentAsOverdue($paymentId)
    {
        $payment = $this->paymentInScope((int) $paymentId);
        $this->authorizeAbility('expenses.pay', $payment);

        $payment->markAsOverdue();

        session()->flash('message', __('Payment marked as overdue.'));
        $this->jobSite->refresh();

        if ($this->showExpenseModal && $this->expenseModalMode === 'view') {
            $this->openExpenseViewModal($payment->expense_id);
        }
    }

    // Change the due date of an installment (shows inline date picker)
    public function startEditDueDate($paymentId)
    {
        $payment = $this->paymentInScope((int) $paymentId);
        $this->authorizeAbility('expenses.edit', $payment);

        $this->editDueDateId = $payment->id;
        $this->editDueDate = $payment->due_date->format('Y-m-d');
    }

    public function cancelEditDueDate()
    {
        $this->reset(['editDueDateId', 'editDueDate']);
    }

    public function confirmEditDueDate()
    {
        $this->validate(['editDueDate' => 'required|date']);

        $payment = $this->paymentInScope((int) $this->editDueDateId);
        $this->authorizeAbility('expenses.edit', $payment);

        if (!$payment->isPaid()) {
            $payment->changeDueDate(\Carbon\Carbon::parse($this->editDueDate));
            session()->flash('message', __('Due date updated.'));
        }

        $expenseId = $payment->expense_id;
        $this->cancelEditDueDate();
        $this->jobSite->refresh();

        if ($this->showExpenseModal && $this->expenseModalMode === 'view') {
            $this->openExpenseViewModal($expenseId);
        }
    }

    // Revert a paid one-time expense back to unpaid — `expenses.edit_paid`
    public function unmarkExpensePaid($expenseId)
    {
        $expense = $this->expenseInScope((int) $expenseId);
        $this->authorizeAbility('expenses.edit_paid', $expense);

        $expense->unmarkAsPaid();

        session()->flash('message', __('Expense payment reverted to unpaid.'));
        $this->jobSite->refresh();
    }

    // Revert a paid installment back to pending — `expenses.edit_paid`
    public function unmarkPaymentPaid($paymentId)
    {
        $payment = $this->paymentInScope((int) $paymentId);
        $this->authorizeAbility('expenses.edit_paid', $payment);

        if ($payment->isPaid()) {
            $payment->markAsPending();
            session()->flash('message', __('Payment reverted to pending.'));
        }

        $this->jobSite->refresh();

        if ($this->showExpenseModal && $this->expenseModalMode === 'view') {
            $this->openExpenseViewModal($payment->expense_id);
        }
    }

    // =========================================================================
    // DELETE JOB SITE
    // =========================================================================

    public function confirmDeleteJobSite()
    {
        $this->authorizeAbility('projects.delete', $this->jobSite);

        $jobSite = JobSite::withCount([
            'expenses',
            'changeOrders',
            'dailyReports',
        ])->findOrFail($this->jobSite->id);

        $hasBudget = $jobSite->budget()->exists() ? 1 : 0;

        $this->deleteJobSiteData = [
            'name' => $jobSite->job_site_name,
            'expenses' => $jobSite->expenses_count,
            'change_orders' => $jobSite->change_orders_count,
            'daily_reports' => $jobSite->daily_reports_count,
            'budgets' => $hasBudget,
        ];

        $this->showDeleteJobSiteModal = true;
        $this->dispatch('open-modal', 'delete-jobsite-modal');
    }

    public function deleteJobSite()
    {
        $this->authorizeAbility('projects.delete', $this->jobSite);

        $projectId = $this->jobSite->project_id;

        DB::transaction(function () {
            $this->cleanupJobSiteFiles($this->jobSite->id);
            $this->jobSite->delete();
        });

        session()->flash('message', __('Job site deleted successfully!'));
        return $this->redirect(route('projects.jobsites', $projectId), navigate: true);
    }

    public function cancelDeleteJobSite()
    {
        $this->showDeleteJobSiteModal = false;
        $this->deleteJobSiteData = [];
        $this->dispatch('close-modal', 'delete-jobsite-modal');
    }

    protected function cleanupJobSiteFiles($jobSiteId)
    {
        // Delete expense receipt files
        $receiptPaths = Expense::where('job_site_id', $jobSiteId)
            ->whereNotNull('receipt_path')
            ->pluck('receipt_path');

        foreach ($receiptPaths as $path) {
            Storage::delete($path);
        }

        // Delete change order files
        $changeOrderPaths = ChangeOrder::where('job_site_id', $jobSiteId)
            ->whereNotNull('file_path')
            ->pluck('file_path');

        foreach ($changeOrderPaths as $path) {
            Storage::delete($path);
        }

        // Delete daily report images (polymorphic - won't cascade)
        $dailyReportIds = DailyReport::where('job_site_id', $jobSiteId)->pluck('id');

        if ($dailyReportIds->isNotEmpty()) {
            $imagePaths = DailyReportImage::whereIn('imageable_id', $dailyReportIds)
                ->where('imageable_type', DailyReport::class)
                ->pluck('file_path');

            foreach ($imagePaths as $path) {
                Storage::delete($path);
            }

            // Also get manpower log images
            $manpowerIds = DailyReportManpower::whereIn('daily_report_id', $dailyReportIds)->pluck('id');

            if ($manpowerIds->isNotEmpty()) {
                $manpowerImagePaths = DailyReportImage::whereIn('imageable_id', $manpowerIds)
                    ->where('imageable_type', DailyReportManpower::class)
                    ->pluck('file_path');

                foreach ($manpowerImagePaths as $path) {
                    Storage::delete($path);
                }
            }
        }
    }

    public function render()
    {
        $changeOrders = $this->changeOrderQuery()
            ->orderByDesc('requested_date')
            ->orderByDesc('id')
            ->get();

        // Expenses
        $expensesQuery = $this->jobSite->expenses()->with(['catalogItem', 'createdBy', 'payments', 'items.budgetItem']);

        // Apply cost code filter
        if ($this->expenseCostCodeFilter !== 'all') {
            $expensesQuery->whereHas('items.budgetItem', function ($q) {
                $q->where('code', $this->expenseCostCodeFilter);
            });
        }

        // Apply status filter
        if ($this->expenseStatusFilter !== 'all') {
            $expensesQuery->where('status', $this->expenseStatusFilter);
        }

        if ($this->expenseSearch) {
            $expensesQuery->where(function($query) {
                $query->where('item_name', 'like', '%' . $this->expenseSearch . '%')
                    ->orWhere('notes', 'like', '%' . $this->expenseSearch . '%');
            });
        }

        $expenses = $expensesQuery->orderBy('expense_date', 'desc')->get();
        $totalExpensesAmount = $expenses->sum('total_amount');

        // Calculate payment totals
        $totalPaidAmount = $expenses->sum(fn($e) => $e->getPaidAmount());
        $totalPendingAmount = $expenses->sum(fn($e) => $e->getPendingAmount());

        // Catalog items for search
        $catalogItems = collect();
        if ($this->catalogItemSearch && strlen($this->catalogItemSearch) >= 2) {
            $catalogItems = CatalogItem::where('is_active', true)
                ->where('name', 'like', '%' . $this->catalogItemSearch . '%')
                ->take(10)
                ->get();
        }

        // Daily Reports
        $dailyReports = $this->jobSite->dailyReports()
            ->with(['preparedBy', 'tasks.images'])
            ->orderBy('report_date', 'desc')
            ->get();

        // Get the viewing expense with payments for the modal
        $viewingExpense = null;
        if ($this->editingExpense && $this->expenseModalMode === 'view') {
            $viewingExpense = Expense::with(['payments.paidBy', 'paidBy'])->find($this->editingExpense);
        }

        // Budget
        $budget = $this->jobSite->budget?->load(['sourceTemplate', 'parentItems']);

        // Only the budget tab shows these figures, and building them walks every
        // contract, expense and purchase order for the location.
        $budgetLedger = ($budget && $this->activeTab === 'budget')
            ? CostCodeLedger::for($budget)
            : null;

        // Purchase Orders
        $purchaseOrders = PurchaseOrder::where('job_site_id', $this->jobSite->id)
            ->with(['supplier', 'createdBy'])
            ->orderBy('created_at', 'desc')
            ->get();

        $purchaseOrderStats = [
            'total' => $purchaseOrders->count(),
            'pending' => $purchaseOrders->where('status', 'pending')->count(),
            'approved' => $purchaseOrders->where('status', 'approved')->count(),
            'totalAmount' => $purchaseOrders->sum('total_amount'),
            'approvedAmount' => $purchaseOrders->where('status', 'approved')->sum('total_amount'),
        ];

        return view('livewire.job-site.job-site-show', [
            'changeOrders' => $changeOrders,
            'changeOrderSummary' => $this->changeOrderSummary($changeOrders),
            'coBudget' => $this->changeOrderBudget(),
            'coLineSuggestions' => $this->changeOrderLineSearchResults(),
            'changeOrderRecord' => $this->editingChangeOrder
                ? ChangeOrder::with(['createdBy', 'approvedBy'])->find($this->editingChangeOrder)
                : null,
            'expenses' => $expenses,
            'totalExpensesAmount' => $totalExpensesAmount,
            'totalPaidAmount' => $totalPaidAmount,
            'totalPendingAmount' => $totalPendingAmount,
            'catalogItems' => $catalogItems,
            'dailyReports' => $dailyReports,
            'viewingExpense' => $viewingExpense,
            'budget' => $budget,
            'expenseCostCodes' => $budget
                ? $budget->items()->orderBy('code')->get(['id', 'code', 'name'])
                : collect(),
            'budgetTotals' => $budgetLedger?->totals(),
            'budgetLedgerRows' => $budgetLedger?->rowsByItem() ?? [],
            'purchaseOrders' => $purchaseOrders,
            'purchaseOrderStats' => $purchaseOrderStats,
        ])->layout('components.layouts.app');
    }
}
