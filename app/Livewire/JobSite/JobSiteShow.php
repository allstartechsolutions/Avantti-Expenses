<?php

namespace App\Livewire\JobSite;

use App\Models\CatalogItem;
use App\Models\ChangeOrder;
use App\Models\DailyReport;
use App\Models\DailyReportImage;
use App\Models\DailyReportTask;
use App\Models\Expense;
use App\Models\JobSite;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

class JobSiteShow extends Component
{
    use WithFileUploads;

    public JobSite $jobSite;
    public $activeTab = 'overview';

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

    protected function rules()
    {
        $rules = [
            'title' => 'required|string|max:255',
            'requested_date' => 'required|date',
            'description' => 'nullable|string',
            'amount' => 'required|numeric|min:0',
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

    public function mount(JobSite $jobSite)
    {
        $this->jobSite = $jobSite->load('project');
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

            session()->flash('message', 'Change order updated successfully!');
        } else {
            ChangeOrder::create([
                'job_site_id' => $this->jobSite->id,
                'title' => $this->title,
                'requested_date' => $this->requested_date,
                'description' => $this->description,
                'amount' => $this->amount,
                'file_path' => $filePath,
                'created_by' => Auth::id(),
            ]);

            session()->flash('message', 'Change order created successfully!');
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

        session()->flash('message', 'Change order deleted successfully!');
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
        $this->reset(['catalogItemSearch', 'selectedCatalogItem', 'isCustomItem', 'expense_item_name', 'expense_item_type', 'expense_purchase_unit', 'expense_usage_unit', 'expense_unit_type_used', 'expense_quantity', 'expense_unit_price', 'expense_total_amount', 'expense_notes', 'expense_date', 'expense_receipt', 'existingReceiptPath', 'editingExpense']);
        $this->expense_date = now()->format('Y-m-d');
        $this->expense_unit_type_used = 'custom';
        $this->expenseModalMode = 'create';
        $this->showExpenseModal = true;
        $this->dispatch('open-modal', 'expense-modal');
    }

    public function openExpenseEditModal($expenseId)
    {
        $expense = Expense::findOrFail($expenseId);

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

        $this->expenseModalMode = 'edit';
        $this->showExpenseModal = true;
        $this->dispatch('open-modal', 'expense-modal');
    }

    public function openExpenseViewModal($expenseId)
    {
        $expense = Expense::findOrFail($expenseId);

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

        $this->expenseModalMode = 'view';
        $this->showExpenseModal = true;
        $this->dispatch('open-modal', 'expense-modal');
    }

    public function saveExpense()
    {
        $this->validate([
            'expense_item_name' => 'required|string|max:255',
            'expense_quantity' => 'required|numeric|min:0.01',
            'expense_unit_price' => 'required|numeric|min:0',
            'expense_date' => 'required|date',
            'expense_receipt' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

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
        ];

        if ($this->expenseModalMode === 'edit' && $this->editingExpense) {
            $expense = Expense::findOrFail($this->editingExpense);
            $expense->update($data);
            session()->flash('message', 'Expense updated successfully!');
        } else {
            $data['created_by'] = Auth::id();
            Expense::create($data);
            session()->flash('message', 'Expense added successfully!');
        }

        $this->closeExpenseModal();
        $this->jobSite->refresh();
    }

    public function deleteExpense($expenseId)
    {
        $expense = Expense::findOrFail($expenseId);
        $expense->delete();

        session()->flash('message', 'Expense deleted successfully!');
        $this->jobSite->refresh();
    }

    public function closeExpenseModal()
    {
        $this->showExpenseModal = false;
        $this->reset(['catalogItemSearch', 'selectedCatalogItem', 'isCustomItem', 'expense_item_name', 'expense_item_type', 'expense_purchase_unit', 'expense_usage_unit', 'expense_unit_type_used', 'expense_quantity', 'expense_unit_price', 'expense_total_amount', 'expense_notes', 'expense_date', 'expense_receipt', 'existingReceiptPath', 'editingExpense']);
        $this->dispatch('close-modal', 'expense-modal');
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
        $expensesQuery = $this->jobSite->expenses()->with(['catalogItem', 'createdBy']);

        if ($this->expenseSearch) {
            $expensesQuery->where(function($query) {
                $query->where('item_name', 'like', '%' . $this->expenseSearch . '%')
                    ->orWhere('notes', 'like', '%' . $this->expenseSearch . '%');
            });
        }

        $expenses = $expensesQuery->orderBy('expense_date', 'desc')->get();
        $totalExpensesAmount = $expenses->sum('total_amount');

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

        return view('livewire.job-site.job-site-show', [
            'changeOrders' => $changeOrders,
            'totalChangeOrdersAmount' => $totalChangeOrdersAmount,
            'expenses' => $expenses,
            'totalExpensesAmount' => $totalExpensesAmount,
            'catalogItems' => $catalogItems,
            'dailyReports' => $dailyReports,
        ])->layout('components.layouts.app');
    }
}
