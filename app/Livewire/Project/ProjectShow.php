<?php

namespace App\Livewire\Project;

use App\Livewire\Concerns\AuthorizesAbility;
use App\Enums\JobSiteStatus;
use App\Livewire\Concerns\ManagesChangeOrders;
use App\Models\CatalogItem;
use App\Models\ChangeOrder;
use App\Models\DailyReportImage;
use App\Models\DailyReportManpower;
use App\Models\DailyReport;
use App\Models\Expense;
use App\Models\ExpenseItem;
use App\Models\JobSite;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\BudgetService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

class ProjectShow extends Component
{
    use AuthorizesAbility;

    use WithFileUploads, ManagesChangeOrders;

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
    public $expense_supplier_id = null;
    public $supplierSearch = '';
    public $expense_notes = '';
    public $expense_date = '';
    public $expense_receipt = null;
    public $existingReceiptPath = null;
    public $expense_total_amount = 0;

    // Expense change history (shown in view modal)
    public $expenseHistory = [];

    // Mark-as-paid confirmation state (inline date picker)
    public $markPaidType = null; // 'expense' or 'payment'
    public $markPaidId = null;
    public $markPaidDate = '';

    // Installment due-date editing state
    public $editDueDateId = null;
    public $editDueDate = '';

    // Expense items (multi-item support)
    public $expenseItems = [];
    public $catalogItemSearches = []; // Search text per item row
    public $budgetItems = []; // Available budget items for dropdown

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
    public $expenseStatusFilter = 'all'; // Filter for expense list

    // Change Order list filter (the rest lives in ManagesChangeOrders)
    public $changeOrderLocationFilter = 'all';

    // Daily Reports properties
    public $dailyReportSearch = '';
    public $dailyReportLocationFilter = 'all';

    // Delete Project modal
    public $showDeleteProjectModal = false;
    public $deleteProjectData = [];

    // Delete Job Site modal
    public $showDeleteJobSiteModal = false;
    public $deletingJobSiteId = null;
    public $deleteJobSiteData = [];

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
        if ($tab && in_array($tab, ['overview', 'jobsites', 'expenses', 'change-orders', 'daily-reports', 'budget'])) {
            $this->activeTab = $tab;
        }

        $this->authorizeTab($this->activeTab);
    }

    public function setActiveTab($tab)
    {
        $this->authorizeTab($tab);

        $this->activeTab = $tab;
    }

    /**
     * This legacy screen carries six tabs in one component, so the guard is on
     * the tab and not the route — `setActiveTab` is callable from the browser.
     * Only the swept modules answer here; the rest keep their old rules until
     * their own pass.
     */
    protected function authorizeTab(string $tab): void
    {
        $abilities = [
            'expenses' => 'expenses.view',
            'change-orders' => 'change-orders.view',
            'daily-reports' => 'daily-reports.view',
            'budget' => 'budget.view',
        ];

        if (isset($abilities[$tab])) {
            $this->authorizeAbility($abilities[$tab], $this->project);
        }
    }

    public function openJobSiteForm()
    {
        $this->authorizeAbility('project.edit', $this->project);

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
        $this->authorizeAbility('project.edit', $this->project);

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
        $this->authorizeAbility('project.edit', $this->project);

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

            session()->flash('message', __('Job site updated successfully!'));
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

            session()->flash('message', __('Job site created successfully!'));
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

    /**
     * Get empty expense item structure
     */
    protected function getEmptyExpenseItem(): array
    {
        return [
            'id' => null,
            'budget_item_id' => null,
            'catalog_item_id' => null,
            'item_name' => '',
            'item_type' => 'custom',
            'description' => '',
            'quantity' => 1,
            'unit' => '',
            'unit_price' => '',
            'total_amount' => 0,
        ];
    }

    /**
     * Add a new expense item row
     */
    public function addExpenseItem()
    {
        $this->expenseItems[] = $this->getEmptyExpenseItem();
        $this->catalogItemSearches[] = '';
    }

    /**
     * Remove an expense item row
     */
    public function removeExpenseItem($index)
    {
        if (count($this->expenseItems) > 1) {
            unset($this->expenseItems[$index]);
            unset($this->catalogItemSearches[$index]);
            $this->expenseItems = array_values($this->expenseItems);
            $this->catalogItemSearches = array_values($this->catalogItemSearches);
            $this->calculateExpenseTotal();
        }
    }

    /**
     * Select a catalog item for a specific expense item row
     */
    public function selectCatalogItemForRow($itemId, $rowIndex)
    {
        $item = CatalogItem::find($itemId);

        if ($item && isset($this->expenseItems[$rowIndex])) {
            $this->expenseItems[$rowIndex]['catalog_item_id'] = $itemId;
            $this->expenseItems[$rowIndex]['item_name'] = $item->name;
            $this->expenseItems[$rowIndex]['item_type'] = 'catalog';
            $this->expenseItems[$rowIndex]['unit'] = $item->usage_unit ?? $item->purchase_unit ?? '';
            $this->expenseItems[$rowIndex]['unit_price'] = $item->unit_cost ?? $item->current_cost ?? 0;
            $this->catalogItemSearches[$rowIndex] = $item->name;

            $this->calculateItemTotal($rowIndex);
        }
    }

    /**
     * Clear catalog item selection for a row (switch to custom)
     */
    public function clearCatalogItemForRow($rowIndex)
    {
        if (isset($this->expenseItems[$rowIndex])) {
            $this->expenseItems[$rowIndex]['catalog_item_id'] = null;
            $this->expenseItems[$rowIndex]['item_type'] = 'custom';
            $this->catalogItemSearches[$rowIndex] = '';
        }
    }

    /**
     * Calculate total for a specific item row
     */
    public function calculateItemTotal($index)
    {
        if (isset($this->expenseItems[$index])) {
            $qty = floatval($this->expenseItems[$index]['quantity'] ?? 0);
            $price = floatval($this->expenseItems[$index]['unit_price'] ?? 0);
            $this->expenseItems[$index]['total_amount'] = round($qty * $price, 2);
            $this->calculateExpenseTotal();
        }
    }

    /**
     * Calculate total expense amount from all items
     */
    public function calculateExpenseTotal()
    {
        $total = 0;
        foreach ($this->expenseItems as $item) {
            $total += floatval($item['total_amount'] ?? 0);
        }
        $this->expense_total_amount = round($total, 2);
    }

    /**
     * Handle item field updates
     */
    public function updatedExpenseItems($value, $key)
    {
        // Key format: "0.quantity" or "1.unit_price"
        $parts = explode('.', $key);
        if (count($parts) === 2) {
            $index = intval($parts[0]);
            $field = $parts[1];

            if (in_array($field, ['quantity', 'unit_price'])) {
                $this->calculateItemTotal($index);
            }
        }
    }

    /**
     * Handle catalog item search updates - sync to item_name
     */
    public function updatedCatalogItemSearches($value, $key)
    {
        $index = intval($key);
        if (isset($this->expenseItems[$index]) && !$this->expenseItems[$index]['catalog_item_id']) {
            // If no catalog item selected, use the search text as item name
            $this->expenseItems[$index]['item_name'] = $value;
        }
    }

    /**
     * Load budget items for dropdown based on current location
     */
    public function loadBudgetItems()
    {
        $this->budgetItems = BudgetService::getBudgetItemsForDropdown(
            $this->project->id,
            $this->expense_job_site_id
        );
    }

    /**
     * Handle job site change - reload budget items
     */
    public function updatedExpenseJobSiteId()
    {
        $this->loadBudgetItems();
    }

    /**
     * Select a supplier
     */
    public function selectSupplier($supplierId)
    {
        $supplier = Supplier::find($supplierId);
        if ($supplier) {
            $this->expense_supplier_id = $supplierId;
            $this->supplierSearch = $supplier->name;
        }
    }

    /**
     * Clear supplier selection
     */
    public function clearSupplier()
    {
        $this->expense_supplier_id = null;
        $this->supplierSearch = '';
    }

    /** An expense of THIS project, or a 404. */
    protected function expenseInScope(int $expenseId): Expense
    {
        return Expense::where('project_id', $this->project->id)->findOrFail($expenseId);
    }

    /** An installment of an expense of THIS project, or a 404. */
    protected function paymentInScope(int $paymentId): \App\Models\ExpensePayment
    {
        return \App\Models\ExpensePayment::whereHas(
            'expense',
            fn ($q) => $q->where('project_id', $this->project->id)
        )->findOrFail($paymentId);
    }

    /** Where the Location picker is currently pointing. */
    protected function expenseDestination(): \App\Models\JobSite|Project|null
    {
        if ($this->expense_job_site_id) {
            return \App\Models\JobSite::where('project_id', $this->project->id)
                ->find($this->expense_job_site_id);
        }

        return $this->project;
    }

    public function openExpenseCreateModal()
    {
        $this->authorizeAbility('expenses.create', $this->project);

        $this->reset([
            'expense_job_site_id', 'expense_supplier_id', 'supplierSearch',
            'expense_notes', 'expense_date', 'expense_receipt', 'existingReceiptPath', 'editingExpense',
            'expenseItems', 'catalogItemSearches', 'expense_total_amount',
            // Payment fields
            'expense_status', 'expense_payment_method', 'expense_is_auto_payment',
            'expense_has_installments', 'expense_total_installments', 'expense_payment_frequency',
            'expense_payment_due_date', 'expense_paid_date', 'expense_use_custom_amounts',
            'expense_custom_amounts', 'expense_payment_schedule_preview'
        ]);

        // Initialize with one empty item
        $this->expenseItems = [$this->getEmptyExpenseItem()];
        $this->catalogItemSearches = [''];

        $this->expense_date = now()->format('Y-m-d');
        $this->expense_paid_date = now()->format('Y-m-d');
        $this->expense_payment_due_date = now()->format('Y-m-d');
        $this->expense_status = 'paid';
        $this->expense_total_installments = 2;
        $this->expense_payment_frequency = 'monthly';

        $this->loadBudgetItems();

        $this->expenseModalMode = 'create';
        $this->showExpenseModal = true;
        $this->dispatch('open-modal', 'expense-modal');
    }

    public function openExpenseEditModal($expenseId)
    {
        $expense = $this->expenseInScope((int) $expenseId)
            ->load(['payments', 'items.budgetItem', 'items.catalogItem', 'supplier']);

        $this->authorizeAbility('expenses.edit', $expense);

        // Settled money needs `expenses.edit_paid` on top of `expenses.edit`.
        if (!$expense->isEditableBy(auth()->user())) {
            session()->flash('error', __('This expense cannot be edited because it has payments.'));
            return;
        }

        $this->editingExpense = $expense->id;
        $this->expense_job_site_id = $expense->job_site_id;
        $this->expense_supplier_id = $expense->supplier_id;
        $this->supplierSearch = $expense->supplier?->name ?? '';
        $this->expense_notes = $expense->notes;
        $this->expense_date = $expense->expense_date->format('Y-m-d');
        $this->existingReceiptPath = $expense->receipt_path;
        $this->expense_receipt = null;
        $this->expense_total_amount = $expense->total_amount;

        // Load expense items
        $this->expenseItems = [];
        $this->catalogItemSearches = [];

        if ($expense->items->count() > 0) {
            foreach ($expense->items as $item) {
                $this->expenseItems[] = [
                    'id' => $item->id,
                    'budget_item_id' => $item->budget_item_id,
                    'catalog_item_id' => $item->catalog_item_id,
                    'item_name' => $item->item_name,
                    'item_type' => $item->item_type,
                    'description' => $item->description ?? '',
                    'quantity' => $item->quantity,
                    'unit' => $item->unit ?? '',
                    'unit_price' => $item->unit_price,
                    'total_amount' => $item->total_amount,
                ];
                $this->catalogItemSearches[] = $item->catalogItem?->name ?? '';
            }
        } else {
            // Legacy expense without items - create one from header data
            $this->expenseItems[] = [
                'id' => null,
                'budget_item_id' => null,
                'catalog_item_id' => $expense->catalog_item_id,
                'item_name' => $expense->item_name ?? '',
                'item_type' => $expense->catalog_item_id ? 'catalog' : 'custom',
                'description' => '',
                'quantity' => $expense->quantity ?? 1,
                'unit' => $expense->getDisplayUnit(),
                'unit_price' => $expense->unit_price,
                'total_amount' => $expense->total_amount,
            ];
            $this->catalogItemSearches[] = $expense->catalogItem?->name ?? '';
        }

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

        $this->loadBudgetItems();

        $this->expenseModalMode = 'edit';
        $this->showExpenseModal = true;
        $this->dispatch('open-modal', 'expense-modal');
    }

    public function openExpenseViewModal($expenseId)
    {
        $expense = $this->expenseInScope((int) $expenseId)
            ->load(['payments', 'items.budgetItem', 'items.catalogItem', 'supplier']);

        $this->authorizeAbility('expenses.view', $expense);

        $this->editingExpense = $expense->id;
        $this->expense_job_site_id = $expense->job_site_id;
        $this->expense_supplier_id = $expense->supplier_id;
        $this->supplierSearch = $expense->supplier?->name ?? '';
        $this->expense_notes = $expense->notes;
        $this->expense_date = $expense->expense_date->format('Y-m-d');
        $this->existingReceiptPath = $expense->receipt_path;
        $this->expense_total_amount = $expense->total_amount;

        // Load expense items
        $this->expenseItems = [];
        $this->catalogItemSearches = [];

        if ($expense->items->count() > 0) {
            foreach ($expense->items as $item) {
                $this->expenseItems[] = [
                    'id' => $item->id,
                    'budget_item_id' => $item->budget_item_id,
                    'catalog_item_id' => $item->catalog_item_id,
                    'item_name' => $item->item_name,
                    'item_type' => $item->item_type,
                    'description' => $item->description ?? '',
                    'quantity' => $item->quantity,
                    'unit' => $item->unit ?? '',
                    'unit_price' => $item->unit_price,
                    'total_amount' => $item->total_amount,
                ];
                $this->catalogItemSearches[] = $item->catalogItem?->name ?? '';
            }
        }

        // Payment fields
        $this->expense_status = $expense->status;
        $this->expense_payment_method = $expense->payment_method;
        $this->expense_is_auto_payment = $expense->is_auto_payment;
        $this->expense_has_installments = $expense->isInstallment();
        $this->expense_total_installments = $expense->total_installments;
        $this->expense_payment_frequency = $expense->payment_frequency;
        $this->expense_payment_due_date = $expense->payment_due_date?->format('Y-m-d');
        $this->expense_paid_date = $expense->paid_date?->format('Y-m-d');

        $this->loadBudgetItems();
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
        if ($this->expenseModalMode === 'edit' && $this->editingExpense) {
            $existing = $this->expenseInScope((int) $this->editingExpense);

            $this->authorizeAbility('expenses.edit', $existing);

            if (! $existing->isEditable()) {
                $this->authorizeAbility('expenses.edit_paid', $existing);
            }
        } else {
            $this->authorizeAbility('expenses.create', $this->project);
        }

        // …and wherever the Location picker is now pointing.
        $this->authorizeAbility(
            $this->expenseModalMode === 'edit' ? 'expenses.edit' : 'expenses.create',
            $this->expenseDestination(),
        );

        // Validate header fields
        $rules = [
            'expense_date' => 'required|date',
            'expense_receipt' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'expense_job_site_id' => 'nullable|exists:job_sites,id',
            'expense_supplier_id' => 'nullable|exists:vendors,id,is_supplier,1',
            'expense_payment_method' => 'nullable|in:cash,check,credit_card,debit_card,bank_transfer,pix,other',
            'expenseItems' => 'required|array|min:1',
            'expenseItems.*.item_name' => 'required|string|max:255',
            'expenseItems.*.quantity' => 'required|numeric|min:0.01',
            'expenseItems.*.unit_price' => 'required|numeric|min:0',
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

        $this->validate($rules, [
            'expenseItems.required' => 'At least one item is required.',
            'expenseItems.*.item_name.required' => 'Item name is required.',
            'expenseItems.*.quantity.required' => 'Quantity is required.',
            'expenseItems.*.unit_price.required' => 'Unit price is required.',
        ]);

        $receiptPath = $this->existingReceiptPath;

        if ($this->expense_receipt) {
            if ($this->existingReceiptPath) {
                Storage::delete($this->existingReceiptPath);
            }
            $receiptPath = $this->expense_receipt->store('expenses', 'local');
        }

        // Calculate total from items
        $this->calculateExpenseTotal();

        DB::transaction(function () use ($receiptPath) {
            // Prepare header data
            $data = [
                'project_id' => $this->project->id,
                'job_site_id' => $this->expense_job_site_id ?: null,
                'supplier_id' => $this->expense_supplier_id ?: null,
                'total_amount' => $this->expense_total_amount,
                'notes' => $this->expense_notes,
                'receipt_path' => $receiptPath,
                'expense_date' => $this->expense_date,
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

                if (!$expense->isEditableBy(auth()->user())) {
                    throw new \Exception('This expense cannot be edited because it has payments.');
                }

                $expense->updateWithHistory($data);

                // Get existing item IDs
                $existingItemIds = $expense->items()->pluck('id')->toArray();
                $updatedItemIds = [];

                // Update or create items
                foreach ($this->expenseItems as $index => $itemData) {
                    $budgetItemId = $itemData['budget_item_id'] ?: null;

                    // If no budget item selected, assign to the budget default bucket
                    if (!$budgetItemId) {
                        $defaultItem = BudgetService::getDefaultItem(
                            $this->project->id,
                            $this->expense_job_site_id,
                            Auth::id()
                        );
                        $budgetItemId = $defaultItem->id;
                    }

                    $itemPayload = [
                        'budget_item_id' => $budgetItemId,
                        'catalog_item_id' => $itemData['catalog_item_id'] ?: null,
                        'item_name' => $itemData['item_name'],
                        'item_type' => $itemData['item_type'] ?? 'custom',
                        'description' => $itemData['description'] ?? null,
                        'quantity' => $itemData['quantity'],
                        'unit' => $itemData['unit'] ?? null,
                        'unit_price' => $itemData['unit_price'],
                        'total_amount' => floatval($itemData['quantity']) * floatval($itemData['unit_price']),
                        'sort_order' => $index,
                    ];

                    if (!empty($itemData['id'])) {
                        // Update existing item
                        $expense->items()->where('id', $itemData['id'])->update($itemPayload);
                        $updatedItemIds[] = $itemData['id'];
                    } else {
                        // Create new item
                        $newItem = $expense->items()->create($itemPayload);
                        $updatedItemIds[] = $newItem->id;
                    }
                }

                // Delete removed items
                $itemsToDelete = array_diff($existingItemIds, $updatedItemIds);
                if (!empty($itemsToDelete)) {
                    $expense->items()->whereIn('id', $itemsToDelete)->delete();
                }

                // Regenerate payment schedule if installments changed
                // (locked once any installment has been paid)
                if (!$expense->hasLockedPayments()) {
                    if ($this->expense_has_installments) {
                        $customAmounts = $this->expense_use_custom_amounts ? $this->expense_custom_amounts : null;
                        $expense->generatePaymentSchedule($customAmounts);
                    } else {
                        $expense->payments()->delete();
                    }
                }

                session()->flash('message', __('Expense updated successfully!'));
            } else {
                $data['created_by'] = Auth::id();
                $expense = Expense::create($data);

                // Create expense items
                foreach ($this->expenseItems as $index => $itemData) {
                    $budgetItemId = $itemData['budget_item_id'] ?: null;

                    // If no budget item selected, assign to the budget default bucket
                    if (!$budgetItemId) {
                        $defaultItem = BudgetService::getDefaultItem(
                            $this->project->id,
                            $this->expense_job_site_id,
                            Auth::id()
                        );
                        $budgetItemId = $defaultItem->id;
                    }

                    $expense->items()->create([
                        'budget_item_id' => $budgetItemId,
                        'catalog_item_id' => $itemData['catalog_item_id'] ?: null,
                        'item_name' => $itemData['item_name'],
                        'item_type' => $itemData['item_type'] ?? 'custom',
                        'description' => $itemData['description'] ?? null,
                        'quantity' => $itemData['quantity'],
                        'unit' => $itemData['unit'] ?? null,
                        'unit_price' => $itemData['unit_price'],
                        'total_amount' => floatval($itemData['quantity']) * floatval($itemData['unit_price']),
                        'sort_order' => $index,
                    ]);
                }

                // Generate payment schedule for installments
                if ($this->expense_has_installments) {
                    $customAmounts = $this->expense_use_custom_amounts ? $this->expense_custom_amounts : null;
                    $expense->generatePaymentSchedule($customAmounts);
                }

                session()->flash('message', __('Expense added successfully!'));
            }
        });

        $this->closeExpenseModal();
        $this->project->refresh();
    }

    public function deleteExpense($expenseId)
    {
        $expense = $this->expenseInScope((int) $expenseId);

        $this->authorizeAbility('expenses.delete', $expense);

        $expense->delete();

        session()->flash('message', __('Expense deleted successfully!'));
        $this->project->refresh();
    }

    public function closeExpenseModal()
    {
        $this->showExpenseModal = false;
        $this->reset([
            'expense_job_site_id', 'expense_supplier_id', 'supplierSearch',
            'expense_notes', 'expense_date', 'expense_receipt', 'existingReceiptPath', 'editingExpense',
            'expenseItems', 'catalogItemSearches', 'budgetItems', 'expense_total_amount', 'expenseHistory',
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
            // Initialize custom amounts with equal distribution
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
            session()->flash('message', __('Payment marked as paid!'));
        } else {
            $expense = $this->expenseInScope((int) $this->markPaidId);
            $this->authorizeAbility('expenses.pay', $expense);
            if ($expense->isOneTime()) {
                $expense->markAsPaid(null, $paidDate);
            }
            $expenseId = $expense->id;
            session()->flash('message', __('Expense marked as paid!'));
        }

        $this->cancelMarkPaid();
        $this->project->refresh();

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

        session()->flash('message', __('Payment marked as overdue!'));
        $this->project->refresh();

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
        $this->project->refresh();

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
        $this->project->refresh();
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

        $this->project->refresh();

        if ($this->showExpenseModal && $this->expenseModalMode === 'view') {
            $this->openExpenseViewModal($payment->expense_id);
        }
    }

    protected function changeOrderProjectId(): int
    {
        return $this->project->id;
    }

    protected function afterChangeOrderSaved(): void
    {
        $this->project->refresh();
    }

    // =========================================================================
    // DELETE PROJECT
    // =========================================================================

    public function confirmDeleteProject()
    {
        $this->authorizeAbility('projects.delete', $this->project);

        $project = Project::withCount([
            'jobSites',
            'expenses',
            'changeOrders',
            'dailyReports',
            'budgets',
        ])->findOrFail($this->project->id);

        $purchaseOrdersCount = PurchaseOrder::where('project_id', $this->project->id)->count();

        $this->deleteProjectData = [
            'name' => $project->project_name,
            'job_sites' => $project->job_sites_count,
            'expenses' => $project->expenses_count,
            'change_orders' => $project->change_orders_count,
            'daily_reports' => $project->daily_reports_count,
            'budgets' => $project->budgets_count,
            'purchase_orders' => $purchaseOrdersCount,
        ];

        $this->showDeleteProjectModal = true;
        $this->dispatch('open-modal', 'delete-project-modal');
    }

    public function deleteProject()
    {
        $this->authorizeAbility('projects.delete', $this->project);
        DB::transaction(function () {
            $this->cleanupProjectFiles($this->project->id);
            $this->project->delete();
        });

        session()->flash('message', __('Project deleted successfully!'));
        return $this->redirect(route('projects.index'), navigate: true);
    }

    public function cancelDeleteProject()
    {
        $this->showDeleteProjectModal = false;
        $this->deleteProjectData = [];
        $this->dispatch('close-modal', 'delete-project-modal');
    }

    protected function cleanupProjectFiles($projectId)
    {
        // Delete expense receipt files
        $receiptPaths = Expense::where('project_id', $projectId)
            ->whereNotNull('receipt_path')
            ->pluck('receipt_path');

        foreach ($receiptPaths as $path) {
            Storage::delete($path);
        }

        // Delete change order files
        $changeOrderPaths = ChangeOrder::where('project_id', $projectId)
            ->whereNotNull('file_path')
            ->pluck('file_path');

        foreach ($changeOrderPaths as $path) {
            Storage::delete($path);
        }

        // Delete purchase order receipt files
        $poPaths = PurchaseOrder::where('project_id', $projectId)
            ->whereNotNull('receipt_path')
            ->pluck('receipt_path');

        foreach ($poPaths as $path) {
            Storage::delete($path);
        }

        // Delete daily report images (polymorphic - won't cascade)
        $dailyReportIds = DailyReport::where('project_id', $projectId)->pluck('id');

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

    // =========================================================================
    // DELETE JOB SITE
    // =========================================================================

    public function confirmDeleteJobSite($jobSiteId)
    {
        $this->authorizeAbility('projects.delete', $this->project);

        $jobSite = JobSite::withCount([
            'expenses',
            'changeOrders',
            'dailyReports',
        ])->findOrFail($jobSiteId);

        $hasBudget = $jobSite->budget()->exists() ? 1 : 0;

        $this->deletingJobSiteId = $jobSiteId;
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
        $this->authorizeAbility('projects.delete', $this->project);

        $jobSite = JobSite::findOrFail($this->deletingJobSiteId);

        DB::transaction(function () use ($jobSite) {
            $this->cleanupJobSiteFiles($jobSite->id);
            $jobSite->delete();
        });

        $this->showDeleteJobSiteModal = false;
        $this->deletingJobSiteId = null;
        $this->deleteJobSiteData = [];

        session()->flash('message', __('Job site deleted successfully!'));
        $this->project->refresh();
    }

    public function cancelDeleteJobSite()
    {
        $this->showDeleteJobSiteModal = false;
        $this->deletingJobSiteId = null;
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
        $expensesQuery = $this->project->expenses()->with(['jobSite', 'supplier', 'createdBy', 'payments', 'items.budgetItem']);

        // Apply location filter
        if ($this->expenseLocationFilter === 'project') {
            $expensesQuery->whereNull('job_site_id');
        } elseif ($this->expenseLocationFilter !== 'all' && is_numeric($this->expenseLocationFilter)) {
            $expensesQuery->where('job_site_id', $this->expenseLocationFilter);
        }

        // Apply status filter
        if ($this->expenseStatusFilter !== 'all') {
            $expensesQuery->where('status', $this->expenseStatusFilter);
        }

        // Apply search filter - search in items and notes
        if ($this->expenseSearch) {
            $expensesQuery->where(function($query) {
                $query->where('notes', 'like', '%' . $this->expenseSearch . '%')
                    ->orWhereHas('items', function($itemQuery) {
                        $itemQuery->where('item_name', 'like', '%' . $this->expenseSearch . '%');
                    })
                    ->orWhereHas('supplier', function($supplierQuery) {
                        $supplierQuery->where('name', 'like', '%' . $this->expenseSearch . '%');
                    });
            });
        }

        $expenses = $expensesQuery->orderBy('expense_date', 'desc')->get();
        $totalExpensesAmount = $expenses->sum('total_amount');

        // Calculate payment totals for summary
        $totalPaidAmount = $expenses->sum(fn($e) => $e->getPaidAmount());
        $totalPendingAmount = $expenses->sum(fn($e) => $e->getPendingAmount());

        // Catalog items for expense form search (search across all catalog item searches)
        $catalogItems = collect();
        $activeSearchIndex = null;
        foreach ($this->catalogItemSearches as $index => $search) {
            if ($search && strlen($search) >= 2 && !isset($this->expenseItems[$index]['catalog_item_id'])) {
                $activeSearchIndex = $index;
                $catalogItems = CatalogItem::where('is_active', true)
                    ->where('name', 'like', '%' . $search . '%')
                    ->take(10)
                    ->get();
                break;
            }
        }

        // Suppliers for dropdown search
        $suppliers = collect();
        if ($this->supplierSearch && strlen($this->supplierSearch) >= 2 && !$this->expense_supplier_id) {
            $suppliers = Supplier::where('name', 'like', '%' . $this->supplierSearch . '%')
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
        $changeOrders = $changeOrdersQuery->orderByDesc('requested_date')->orderByDesc('id')->get();

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

        // Get the viewing expense with payments for the modal
        $viewingExpense = null;
        if ($this->editingExpense && $this->expenseModalMode === 'view') {
            $viewingExpense = Expense::with(['payments.paidBy', 'paidBy'])->find($this->editingExpense);
        }

        // Budget data
        $projectBudget = $this->project->budget;
        $jobSiteBudgets = $this->project->budgets()
            ->whereNotNull('job_site_id')
            ->with(['jobSite', 'items'])
            ->get();

        return view('livewire.project.project-show', [
            'jobSites' => $jobSites,
            'statuses' => $statuses,
            'expenses' => $expenses,
            'totalExpensesAmount' => $totalExpensesAmount,
            'totalPaidAmount' => $totalPaidAmount,
            'totalPendingAmount' => $totalPendingAmount,
            'catalogItems' => $catalogItems,
            'activeSearchIndex' => $activeSearchIndex,
            'suppliers' => $suppliers,
            'changeOrders' => $changeOrders,
            'changeOrderSummary' => $this->changeOrderSummary($changeOrders),
            'coBudget' => $this->changeOrderBudget(),
            'coLineSuggestions' => $this->changeOrderLineSearchResults(),
            'changeOrderRecord' => $this->editingChangeOrder
                ? ChangeOrder::with(['createdBy', 'approvedBy'])->find($this->editingChangeOrder)
                : null,
            'dailyReports' => $dailyReports,
            'viewingExpense' => $viewingExpense,
            'projectBudget' => $projectBudget,
            'jobSiteBudgets' => $jobSiteBudgets,
        ])->layout('components.layouts.app');
    }
}

