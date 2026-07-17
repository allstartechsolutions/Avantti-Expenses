<?php

namespace App\Livewire\JobSite;

use App\Livewire\Concerns\AuthorizesAdmin;
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
    use WithFileUploads, AuthorizesAdmin;

    public JobSite $jobSite;
    public $activeTab = 'overview';

    // Delete Job Site modal
    public $showDeleteJobSiteModal = false;
    public $deleteJobSiteData = [];

    // Change Order search
    public $changeOrderSearch = '';

    // Change Order modal state
    public $showChangeOrderModal = false;
    public $modalMode = 'create';
    public $editingChangeOrder = null;

    // Change Order form properties
    public $title = '';
    public $requested_date = '';
    public $description = '';
    public $amount = '';
    public $file = null;
    public $existingFilePath = null;

    // Expense properties
    public $expenseSearch = '';
    public $showExpenseModal = false;
    public $expenseModalMode = 'create';
    public $editingExpense = null;
    public $expenseHistory = [];

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

    protected function rules()
    {
        $rules = [
            'title' => 'required|string|max:255',
            'requested_date' => 'required|date',
            'description' => 'nullable|string',
            'amount' => 'required|numeric',
        ];

        if ($this->modalMode === 'create') {
            $rules['file'] = 'nullable|file|max:10240';
        } else {
            $rules['file'] = 'nullable|file|max:10240';
        }

        return $rules;
    }

    protected $validationAttributes = [
        'title' => 'title',
        'requested_date' => 'requested date',
        'description' => 'description',
        'amount' => 'amount',
        'file' => 'file',
    ];

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
    }

    public function setActiveTab($tab)
    {
        $this->activeTab = $tab;
    }

    public function openCreateModal()
    {
        $this->reset(['title', 'requested_date', 'description', 'amount', 'file', 'existingFilePath', 'editingChangeOrder']);
        $this->requested_date = now()->format('Y-m-d');
        $this->modalMode = 'create';
        $this->showChangeOrderModal = true;
        $this->dispatch('open-modal', 'change-order-modal');
    }

    public function openEditModal($changeOrderId)
    {
        $changeOrder = ChangeOrder::findOrFail($changeOrderId);

        $this->editingChangeOrder = $changeOrder->id;
        $this->title = $changeOrder->title;
        $this->requested_date = $changeOrder->requested_date->format('Y-m-d');
        $this->description = $changeOrder->description;
        $this->amount = $changeOrder->amount;
        $this->existingFilePath = $changeOrder->file_path;
        $this->file = null;

        $this->modalMode = 'edit';
        $this->showChangeOrderModal = true;
        $this->dispatch('open-modal', 'change-order-modal');
    }

    public function openViewModal($changeOrderId)
    {
        $changeOrder = ChangeOrder::findOrFail($changeOrderId);

        $this->editingChangeOrder = $changeOrder->id;
        $this->title = $changeOrder->title;
        $this->requested_date = $changeOrder->requested_date->format('Y-m-d');
        $this->description = $changeOrder->description;
        $this->amount = $changeOrder->amount;
        $this->existingFilePath = $changeOrder->file_path;

        $this->modalMode = 'view';
        $this->showChangeOrderModal = true;
        $this->dispatch('open-modal', 'change-order-modal');
    }

    public function saveChangeOrder()
    {
        $this->validate();

        $filePath = $this->existingFilePath;

        if ($this->file) {
            if ($this->existingFilePath) {
                Storage::delete($this->existingFilePath);
            }
            $filePath = $this->file->store('change_orders', 'local');
        }

        if ($this->modalMode === 'edit' && $this->editingChangeOrder) {
            $changeOrder = ChangeOrder::findOrFail($this->editingChangeOrder);
            $changeOrder->update([
                'title' => $this->title,
                'requested_date' => $this->requested_date,
                'description' => $this->description,
                'amount' => $this->amount,
                'file_path' => $filePath,
            ]);

            session()->flash('message', __('Change order updated successfully!'));
        } else {
            ChangeOrder::create([
                'project_id' => $this->jobSite->project_id,
                'job_site_id' => $this->jobSite->id,
                'title' => $this->title,
                'requested_date' => $this->requested_date,
                'description' => $this->description,
                'amount' => $this->amount,
                'file_path' => $filePath,
                'created_by' => Auth::id(),
            ]);

            session()->flash('message', __('Change order created successfully!'));
        }

        $this->closeModal();
        $this->jobSite->refresh();
    }

    public function deleteChangeOrder($changeOrderId)
    {
        $changeOrder = ChangeOrder::findOrFail($changeOrderId);

        if ($changeOrder->file_path) {
            Storage::delete($changeOrder->file_path);
        }

        $changeOrder->delete();

        session()->flash('message', __('Change order deleted successfully!'));
        $this->jobSite->refresh();
    }

    public function closeModal()
    {
        $this->showChangeOrderModal = false;
        $this->reset(['title', 'requested_date', 'description', 'amount', 'file', 'existingFilePath', 'editingChangeOrder']);
        $this->dispatch('close-modal', 'change-order-modal');
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
            'expense_custom_amounts', 'expense_payment_schedule_preview'
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
        $expense = Expense::with('payments')->findOrFail($expenseId);

        // Check if expense is editable (admins can edit paid expenses)
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
        $expense = Expense::with('payments')->findOrFail($expenseId);

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

    public function saveExpense()
    {
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
            $expense = Expense::findOrFail($this->editingExpense);

            // Check if editable (admins can edit paid expenses)
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
        $this->authorizeAdmin();

        $expense = Expense::findOrFail($expenseId);
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
            'expense_custom_amounts', 'expense_payment_schedule_preview'
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

    // Mark payment as paid
    public function markPaymentAsPaid($paymentId)
    {
        $payment = \App\Models\ExpensePayment::findOrFail($paymentId);
        $payment->markAsPaid($this->expense_payment_method);

        session()->flash('message', __('Payment marked as paid.'));
        $this->jobSite->refresh();

        // Refresh the view modal if open
        if ($this->showExpenseModal && $this->expenseModalMode === 'view') {
            $this->openExpenseViewModal($payment->expense_id);
        }
    }

    // Mark payment as overdue
    public function markPaymentAsOverdue($paymentId)
    {
        $payment = \App\Models\ExpensePayment::findOrFail($paymentId);
        $payment->markAsOverdue();

        session()->flash('message', __('Payment marked as overdue.'));
        $this->jobSite->refresh();

        if ($this->showExpenseModal && $this->expenseModalMode === 'view') {
            $this->openExpenseViewModal($payment->expense_id);
        }
    }

    // Mark one-time expense as paid
    public function markExpenseAsPaid($expenseId)
    {
        $expense = Expense::findOrFail($expenseId);

        if ($expense->isOneTime()) {
            $expense->markAsPaid();
            session()->flash('message', __('Expense marked as paid.'));
            $this->jobSite->refresh();
        }
    }

    // Revert a paid one-time expense back to unpaid (admin only)
    public function unmarkExpensePaid($expenseId)
    {
        $this->authorizeAdmin();

        $expense = Expense::findOrFail($expenseId);
        $expense->unmarkAsPaid();

        session()->flash('message', __('Expense payment reverted to unpaid.'));
        $this->jobSite->refresh();
    }

    // Revert a paid installment back to pending (admin only)
    public function unmarkPaymentPaid($paymentId)
    {
        $this->authorizeAdmin();

        $payment = \App\Models\ExpensePayment::findOrFail($paymentId);

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
        $projectId = $this->jobSite->project_id;

        DB::transaction(function () {
            $this->cleanupJobSiteFiles($this->jobSite->id);
            $this->jobSite->delete();
        });

        session()->flash('message', 'Job site deleted successfully!');
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
        $changeOrdersQuery = $this->jobSite->changeOrders()->with('createdBy');

        if ($this->changeOrderSearch) {
            $changeOrdersQuery->where(function($query) {
                $query->where('title', 'like', '%' . $this->changeOrderSearch . '%')
                    ->orWhere('description', 'like', '%' . $this->changeOrderSearch . '%');
            });
        }

        $changeOrders = $changeOrdersQuery->orderBy('created_at', 'desc')->get();
        $totalChangeOrdersAmount = $changeOrders->sum('amount');

        // Expenses
        $expensesQuery = $this->jobSite->expenses()->with(['catalogItem', 'createdBy', 'payments']);

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
            'totalChangeOrdersAmount' => $totalChangeOrdersAmount,
            'expenses' => $expenses,
            'totalExpensesAmount' => $totalExpensesAmount,
            'totalPaidAmount' => $totalPaidAmount,
            'totalPendingAmount' => $totalPendingAmount,
            'catalogItems' => $catalogItems,
            'dailyReports' => $dailyReports,
            'viewingExpense' => $viewingExpense,
            'budget' => $budget,
            'purchaseOrders' => $purchaseOrders,
            'purchaseOrderStats' => $purchaseOrderStats,
        ])->layout('components.layouts.app');
    }
}
