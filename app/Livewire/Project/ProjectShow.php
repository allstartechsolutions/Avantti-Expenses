<?php

namespace App\Livewire\Project;

use App\Enums\JobSiteStatus;
use App\Models\CatalogItem;
use App\Models\ChangeOrder;
use App\Models\Expense;
use App\Models\JobSite;
use App\Models\Project;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

class ProjectShow extends Component
{
    use WithFileUploads;

    public Project $project;
    public $activeTab = 'overview';

    // Job Site search and filters
    public $jobSiteSearch = '';

    // Expense properties
    public $expenseSearch = '';
    public $expenseLocationFilter = 'all'; // 'all', 'project', or job_site_id
    public $showExpenseModal = false;
    public $expenseModalMode = 'create';
    public $editingExpense = null;

    // Expense form properties
    public $expense_job_site_id = null; // null = project-level
    public $catalogItemSearch = '';
    public $selectedCatalogItem = null;
    public $isCustomItem = false;
    public $expense_item_name = '';
    public $expense_item_type = '';
    public $expense_purchase_unit = '';
    public $expense_usage_unit = '';
    public $expense_unit_type_used = 'custom';
    public $expense_quantity = '';
    public $expense_unit_price = '';
    public $expense_total_amount = '';
    public $expense_notes = '';
    public $expense_date = '';
    public $expense_receipt = null;
    public $existingReceiptPath = null;

    // Change Order properties
    public $changeOrderSearch = '';
    public $changeOrderLocationFilter = 'all';
    public $showChangeOrderModal = false;
    public $changeOrderModalMode = 'create';
    public $editingChangeOrder = null;

    // Change Order form properties
    public $co_job_site_id = null;
    public $co_title = '';
    public $co_requested_date = '';
    public $co_description = '';
    public $co_amount = '';
    public $co_file = null;
    public $existingFilePath = null;

    // Daily Reports properties
    public $dailyReportSearch = '';
    public $dailyReportLocationFilter = 'all';

    // Job Site form properties
    public $showJobSiteForm = false;
    public $editingJobSite = null;
    public $job_site_name = '';
    public $street = '';
    public $address_2 = '';
    public $city = '';
    public $state = '';
    public $postal_code = '';
    public $neighborhood = '';
    public $latitude = null;
    public $longitude = null;
    public $contact_person = '';
    public $phone = '';
    public $email = '';
    public $job_amount = '';
    public $status = 'created';

    protected function rules()
    {
        return [
            'job_site_name' => 'required|string|max:255',
            'street' => 'nullable|string|max:255',
            'address_2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'neighborhood' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'contact_person' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'required|email|max:255',
            'job_amount' => 'required|numeric|min:0',
            'status' => 'required|in:created,in_progress,completed,on_hold',
        ];
    }

    protected $validationAttributes = [
        'job_site_name' => 'job site name',
        'contact_person' => 'contact person',
        'postal_code' => 'postal code',
        'email' => 'email address',
        'job_amount' => 'job amount',
    ];

    public function mount(Project $project, ?string $tab = null)
    {
        $this->project = $project;

        // Support tab switching via URL parameter
        if ($tab && in_array($tab, ['overview', 'jobsites', 'expenses', 'change-orders', 'daily-reports'])) {
            $this->activeTab = $tab;
        }
    }

    public function setActiveTab($tab)
    {
        $this->activeTab = $tab;
    }

    public function openJobSiteForm()
    {
        $this->reset(['job_site_name', 'street', 'address_2', 'city', 'state', 'postal_code', 'neighborhood', 'latitude', 'longitude', 'contact_person', 'phone', 'email', 'job_amount', 'status', 'editingJobSite']);

        // Pre-populate with project data
        $this->street = $this->project->street ?? '';
        $this->address_2 = $this->project->address_2 ?? '';
        $this->city = $this->project->city ?? '';
        $this->state = $this->project->state ?? '';
        $this->postal_code = $this->project->postal_code ?? '';
        $this->neighborhood = $this->project->neighborhood ?? '';
        $this->latitude = $this->project->latitude;
        $this->longitude = $this->project->longitude;
        $this->contact_person = $this->project->contact_person;
        $this->phone = $this->project->phone ?? '';
        $this->email = $this->project->email;
        $this->status = 'created';

        $this->showJobSiteForm = true;
    }

    public function editJobSite($jobSiteId)
    {
        $jobSite = JobSite::findOrFail($jobSiteId);

        $this->editingJobSite = $jobSite->id;
        $this->job_site_name = $jobSite->job_site_name;
        $this->street = $jobSite->street;
        $this->address_2 = $jobSite->address_2;
        $this->city = $jobSite->city;
        $this->state = $jobSite->state;
        $this->postal_code = $jobSite->postal_code;
        $this->neighborhood = $jobSite->neighborhood;
        $this->latitude = $jobSite->latitude;
        $this->longitude = $jobSite->longitude;
        $this->contact_person = $jobSite->contact_person;
        $this->phone = $jobSite->phone;
        $this->email = $jobSite->email;
        $this->job_amount = $jobSite->job_amount;
        $this->status = $jobSite->status->value;

        $this->showJobSiteForm = true;
    }

    public function saveJobSite()
    {
        $this->validate();

        if ($this->editingJobSite) {
            // Update existing job site
            $jobSite = JobSite::findOrFail($this->editingJobSite);
            $jobSite->update([
                'job_site_name' => $this->job_site_name,
                'street' => $this->street,
                'address_2' => $this->address_2,
                'city' => $this->city,
                'state' => $this->state,
                'postal_code' => $this->postal_code,
                'neighborhood' => $this->neighborhood,
                'country' => config('app.country'),
                'latitude' => $this->latitude,
                'longitude' => $this->longitude,
                'contact_person' => $this->contact_person,
                'phone' => $this->phone,
                'email' => $this->email,
                'job_amount' => $this->job_amount,
                'status' => $this->status,
            ]);

            session()->flash('message', 'Job site updated successfully!');
        } else {
            // Create new job site
            JobSite::create([
                'project_id' => $this->project->id,
                'job_site_name' => $this->job_site_name,
                'street' => $this->street,
                'address_2' => $this->address_2,
                'city' => $this->city,
                'state' => $this->state,
                'postal_code' => $this->postal_code,
                'neighborhood' => $this->neighborhood,
                'country' => config('app.country'),
                'latitude' => $this->latitude,
                'longitude' => $this->longitude,
                'contact_person' => $this->contact_person,
                'phone' => $this->phone,
                'email' => $this->email,
                'job_amount' => $this->job_amount,
                'status' => $this->status,
                'created_by' => Auth::id(),
            ]);

            session()->flash('message', 'Job site created successfully!');
        }

        $this->showJobSiteForm = false;
        $this->project->refresh();
    }

    public function cancelJobSiteForm()
    {
        $this->showJobSiteForm = false;
        $this->reset(['job_site_name', 'street', 'address_2', 'city', 'state', 'postal_code', 'neighborhood', 'latitude', 'longitude', 'contact_person', 'phone', 'email', 'job_amount', 'status', 'editingJobSite']);
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

            if ($item->type === 'product' && $item->usage_unit) {
                $this->expense_unit_type_used = 'usage';
                $this->expense_unit_price = $item->unit_cost;
            } else {
                $this->expense_unit_type_used = 'purchase';
                $this->expense_unit_price = $item->current_cost;
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
        $this->reset(['expense_job_site_id', 'catalogItemSearch', 'selectedCatalogItem', 'isCustomItem', 'expense_item_name', 'expense_item_type', 'expense_purchase_unit', 'expense_usage_unit', 'expense_unit_type_used', 'expense_quantity', 'expense_unit_price', 'expense_total_amount', 'expense_notes', 'expense_date', 'expense_receipt', 'existingReceiptPath', 'editingExpense']);
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
        $this->expense_job_site_id = $expense->job_site_id;
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
        $this->expense_job_site_id = $expense->job_site_id;
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
            'expense_job_site_id' => 'nullable|exists:job_sites,id',
        ]);

        $receiptPath = $this->existingReceiptPath;

        if ($this->expense_receipt) {
            if ($this->existingReceiptPath) {
                Storage::delete($this->existingReceiptPath);
            }
            $receiptPath = $this->expense_receipt->store('expenses', 'local');
        }

        $data = [
            'project_id' => $this->project->id,
            'job_site_id' => $this->expense_job_site_id ?: null,
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
        $this->project->refresh();
    }

    public function deleteExpense($expenseId)
    {
        $expense = Expense::findOrFail($expenseId);
        $expense->delete();

        session()->flash('message', 'Expense deleted successfully!');
        $this->project->refresh();
    }

    public function closeExpenseModal()
    {
        $this->showExpenseModal = false;
        $this->reset(['expense_job_site_id', 'catalogItemSearch', 'selectedCatalogItem', 'isCustomItem', 'expense_item_name', 'expense_item_type', 'expense_purchase_unit', 'expense_usage_unit', 'expense_unit_type_used', 'expense_quantity', 'expense_unit_price', 'expense_total_amount', 'expense_notes', 'expense_date', 'expense_receipt', 'existingReceiptPath', 'editingExpense']);
        $this->dispatch('close-modal', 'expense-modal');
    }

    // Change Order methods
    public function openChangeOrderCreateModal()
    {
        $this->reset(['co_job_site_id', 'co_title', 'co_requested_date', 'co_description', 'co_amount', 'co_file', 'existingFilePath', 'editingChangeOrder']);
        $this->co_requested_date = now()->format('Y-m-d');
        $this->changeOrderModalMode = 'create';
        $this->showChangeOrderModal = true;
        $this->dispatch('open-modal', 'change-order-modal');
    }

    public function openChangeOrderEditModal($changeOrderId)
    {
        $changeOrder = ChangeOrder::findOrFail($changeOrderId);

        $this->editingChangeOrder = $changeOrder->id;
        $this->co_job_site_id = $changeOrder->job_site_id;
        $this->co_title = $changeOrder->title;
        $this->co_requested_date = $changeOrder->requested_date->format('Y-m-d');
        $this->co_description = $changeOrder->description;
        $this->co_amount = $changeOrder->amount;
        $this->existingFilePath = $changeOrder->file_path;
        $this->co_file = null;

        $this->changeOrderModalMode = 'edit';
        $this->showChangeOrderModal = true;
        $this->dispatch('open-modal', 'change-order-modal');
    }

    public function openChangeOrderViewModal($changeOrderId)
    {
        $changeOrder = ChangeOrder::findOrFail($changeOrderId);

        $this->editingChangeOrder = $changeOrder->id;
        $this->co_job_site_id = $changeOrder->job_site_id;
        $this->co_title = $changeOrder->title;
        $this->co_requested_date = $changeOrder->requested_date->format('Y-m-d');
        $this->co_description = $changeOrder->description;
        $this->co_amount = $changeOrder->amount;
        $this->existingFilePath = $changeOrder->file_path;

        $this->changeOrderModalMode = 'view';
        $this->showChangeOrderModal = true;
        $this->dispatch('open-modal', 'change-order-modal');
    }

    public function saveChangeOrder()
    {
        $this->validate([
            'co_title' => 'required|string|max:255',
            'co_requested_date' => 'required|date',
            'co_description' => 'nullable|string',
            'co_amount' => 'required|numeric|min:0',
            'co_file' => 'nullable|file|max:10240',
            'co_job_site_id' => 'nullable|exists:job_sites,id',
        ]);

        $filePath = $this->existingFilePath;

        if ($this->co_file) {
            if ($this->existingFilePath) {
                Storage::delete($this->existingFilePath);
            }
            $filePath = $this->co_file->store('change_orders', 'local');
        }

        $data = [
            'project_id' => $this->project->id,
            'job_site_id' => $this->co_job_site_id ?: null,
            'title' => $this->co_title,
            'requested_date' => $this->co_requested_date,
            'description' => $this->co_description,
            'amount' => $this->co_amount,
            'file_path' => $filePath,
        ];

        if ($this->changeOrderModalMode === 'edit' && $this->editingChangeOrder) {
            $changeOrder = ChangeOrder::findOrFail($this->editingChangeOrder);
            $changeOrder->update($data);
            session()->flash('message', 'Change order updated successfully!');
        } else {
            $data['created_by'] = Auth::id();
            ChangeOrder::create($data);
            session()->flash('message', 'Change order created successfully!');
        }

        $this->closeChangeOrderModal();
        $this->project->refresh();
    }

    public function deleteChangeOrder($changeOrderId)
    {
        $changeOrder = ChangeOrder::findOrFail($changeOrderId);

        if ($changeOrder->file_path) {
            Storage::delete($changeOrder->file_path);
        }

        $changeOrder->delete();

        session()->flash('message', 'Change order deleted successfully!');
        $this->project->refresh();
    }

    public function closeChangeOrderModal()
    {
        $this->showChangeOrderModal = false;
        $this->reset(['co_job_site_id', 'co_title', 'co_requested_date', 'co_description', 'co_amount', 'co_file', 'existingFilePath', 'editingChangeOrder']);
        $this->dispatch('close-modal', 'change-order-modal');
    }

    public function render()
    {
        $jobSitesQuery = $this->project->jobSites()->with('createdBy');

        // Apply search filter
        if ($this->jobSiteSearch) {
            $jobSitesQuery->where(function($query) {
                $query->where('job_site_name', 'like', '%' . $this->jobSiteSearch . '%')
                    ->orWhere('contact_person', 'like', '%' . $this->jobSiteSearch . '%')
                    ->orWhere('email', 'like', '%' . $this->jobSiteSearch . '%')
                    ->orWhere('city', 'like', '%' . $this->jobSiteSearch . '%');
            });
        }

        $jobSites = $jobSitesQuery->orderBy('created_at', 'desc')->get();
        $statuses = JobSiteStatus::cases();

        // Expenses query with filters
        $expensesQuery = $this->project->expenses()->with(['jobSite', 'catalogItem', 'createdBy']);

        // Apply location filter
        if ($this->expenseLocationFilter === 'project') {
            $expensesQuery->whereNull('job_site_id');
        } elseif ($this->expenseLocationFilter !== 'all' && is_numeric($this->expenseLocationFilter)) {
            $expensesQuery->where('job_site_id', $this->expenseLocationFilter);
        }

        // Apply search filter
        if ($this->expenseSearch) {
            $expensesQuery->where(function($query) {
                $query->where('item_name', 'like', '%' . $this->expenseSearch . '%')
                    ->orWhere('notes', 'like', '%' . $this->expenseSearch . '%');
            });
        }

        $expenses = $expensesQuery->orderBy('expense_date', 'desc')->get();
        $totalExpensesAmount = $expenses->sum('total_amount');

        // Catalog items for expense form search
        $catalogItems = collect();
        if ($this->catalogItemSearch && strlen($this->catalogItemSearch) >= 2) {
            $catalogItems = CatalogItem::where('is_active', true)
                ->where('name', 'like', '%' . $this->catalogItemSearch . '%')
                ->take(10)
                ->get();
        }

        // Change Orders query with filters
        $changeOrdersQuery = $this->project->changeOrders()->with(['jobSite', 'createdBy']);

        // Apply location filter
        if ($this->changeOrderLocationFilter === 'project') {
            $changeOrdersQuery->whereNull('job_site_id');
        } elseif ($this->changeOrderLocationFilter !== 'all' && is_numeric($this->changeOrderLocationFilter)) {
            $changeOrdersQuery->where('job_site_id', $this->changeOrderLocationFilter);
        }

        // Apply search filter
        if ($this->changeOrderSearch) {
            $changeOrdersQuery->where(function($query) {
                $query->where('title', 'like', '%' . $this->changeOrderSearch . '%')
                    ->orWhere('description', 'like', '%' . $this->changeOrderSearch . '%');
            });
        }

        $changeOrders = $changeOrdersQuery->orderBy('requested_date', 'desc')->get();
        $totalChangeOrdersAmount = $changeOrders->sum('amount');

        // Daily Reports query with filters
        $dailyReportsQuery = $this->project->dailyReports()->with(['jobSite', 'preparedBy', 'tasks']);

        // Apply location filter
        if ($this->dailyReportLocationFilter === 'project') {
            $dailyReportsQuery->whereNull('job_site_id');
        } elseif ($this->dailyReportLocationFilter !== 'all' && is_numeric($this->dailyReportLocationFilter)) {
            $dailyReportsQuery->where('job_site_id', $this->dailyReportLocationFilter);
        }

        // Apply search filter (search in tasks descriptions)
        if ($this->dailyReportSearch) {
            $dailyReportsQuery->where(function($query) {
                $query->whereHas('tasks', function($taskQuery) {
                    $taskQuery->where('description', 'like', '%' . $this->dailyReportSearch . '%');
                })->orWhereHas('preparedBy', function($userQuery) {
                    $userQuery->where('name', 'like', '%' . $this->dailyReportSearch . '%');
                });
            });
        }

        $dailyReports = $dailyReportsQuery->orderBy('report_date', 'desc')->get();

        return view('livewire.project.project-show', [
            'jobSites' => $jobSites,
            'statuses' => $statuses,
            'expenses' => $expenses,
            'totalExpensesAmount' => $totalExpensesAmount,
            'catalogItems' => $catalogItems,
            'changeOrders' => $changeOrders,
            'totalChangeOrdersAmount' => $totalChangeOrdersAmount,
            'dailyReports' => $dailyReports,
        ])->layout('components.layouts.app');
    }
}

