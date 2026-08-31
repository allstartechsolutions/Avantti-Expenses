<?php

namespace App\Livewire\Concerns;

use App\Models\Budget;
use App\Models\BudgetItem;
use App\Models\CatalogItem;
use App\Models\Expense;
use App\Models\Supplier;
use App\Models\JobSite;
use App\Models\Project;
use App\Services\BudgetService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * The expense form — header, payment terms and line items with their cost
 * codes — shared by ExpenseCreate and ExpenseEdit so the two screens can never
 * drift apart.
 *
 * The host component owns the context (which project or job site) and what
 * happens on save; everything the form itself does lives here.
 */
trait ManagesExpenseForm
{
    // Header fields
    public $expense_job_site_id = null;
    public $expense_supplier_id = null;
    public $supplierSearch = '';
    public $expense_date;
    public $expense_notes = '';
    public $expense_receipt = null;

    // Payment fields
    public $expense_status = 'paid';
    public $expense_payment_method = null;
    public $expense_is_auto_payment = false;
    public $expense_has_installments = false;
    public $expense_total_installments = 2;
    public $expense_payment_frequency = 'monthly';
    public $expense_payment_due_date;
    public $expense_paid_date;

    // Items
    public $items = [];
    public $expense_total_amount = 0;

    // Item modal
    public $showItemModal = false;
    public $editingItemIndex = null;

    // Item form fields
    public $item_budget_item_id = null;
    public $budgetItemSearch = '';
    public $item_catalog_item_id = null;
    public $catalogItemSearch = '';
    public $item_is_custom = true;
    public $item_name = '';
    public $item_description = '';
    public $item_quantity = 1;
    public $item_unit = '';
    public $item_unit_price = '';
    public $item_total = 0;

    /** The project the form is writing to. */
    abstract protected function expenseProjectId(): int;

    /**
     * Whether the line amounts may be changed. An expense that came from a
     * purchase order, or one whose installments have started being paid, keeps
     * its amounts — the cost codes stay editable either way, since recoding is
     * not the same as changing what was spent.
     */
    public function amountsAreLocked(): bool
    {
        return false;
    }

    protected function startBlankExpenseForm(): void
    {
        $this->expense_date = now()->format('Y-m-d');
        $this->expense_paid_date = now()->format('Y-m-d');
        $this->expense_payment_due_date = now()->format('Y-m-d');
    }

    /**
     * Fill the form from an existing expense.
     */
    protected function fillFormFromExpense(Expense $expense): void
    {
        $expense->loadMissing(['items.budgetItem', 'items.catalogItem', 'supplier']);

        $this->expense_job_site_id = $expense->job_site_id;
        $this->expense_supplier_id = $expense->supplier_id;
        $this->supplierSearch = $expense->supplier?->name ?? '';
        $this->expense_date = $expense->expense_date->format('Y-m-d');
        $this->expense_notes = $expense->notes ?? '';

        $this->expense_status = $expense->status === 'paid' ? 'paid' : 'unpaid';
        $this->expense_payment_method = $expense->payment_method;
        $this->expense_is_auto_payment = (bool) $expense->is_auto_payment;
        $this->expense_has_installments = (int) $expense->total_installments > 1;
        $this->expense_total_installments = max(2, (int) $expense->total_installments);
        $this->expense_payment_frequency = $expense->payment_frequency ?? 'monthly';
        $this->expense_payment_due_date = $expense->payment_due_date?->format('Y-m-d') ?? now()->format('Y-m-d');
        $this->expense_paid_date = $expense->paid_date?->format('Y-m-d') ?? now()->format('Y-m-d');

        $this->items = $expense->items->map(fn ($item) => [
            'budget_item_id' => $item->budget_item_id,
            'cost_code' => $item->budgetItem ? $item->budgetItem->code . ' - ' . $item->budgetItem->name : null,
            'catalog_item_id' => $item->catalog_item_id,
            'item_name' => $item->item_name,
            'item_type' => $item->item_type ?? 'custom',
            'description' => $item->description,
            'quantity' => $item->quantity,
            'unit' => $item->unit,
            'unit_price' => $item->unit_price,
            'total_amount' => $item->total_amount,
        ])->all();

        $this->calculateExpenseTotal();
    }

    // =========================================================================
    // SUPPLIER
    // =========================================================================

    public function selectSupplier($supplierId)
    {
        $supplier = Supplier::find($supplierId);

        if ($supplier) {
            $this->expense_supplier_id = $supplierId;
            $this->supplierSearch = $supplier->name;
        }
    }

    public function clearSupplier()
    {
        $this->expense_supplier_id = null;
        $this->supplierSearch = '';
    }

    // =========================================================================
    // ITEM MODAL
    // =========================================================================

    public function openAddItemModal()
    {
        $this->resetItemForm();
        $this->editingItemIndex = null;
        $this->showItemModal = true;
    }

    public function openEditItemModal($index)
    {
        $item = $this->items[$index] ?? null;

        if (! $item) {
            return;
        }

        $this->editingItemIndex = $index;
        $this->item_budget_item_id = $item['budget_item_id'];
        $this->item_catalog_item_id = $item['catalog_item_id'];
        $this->item_is_custom = ($item['item_type'] ?? 'custom') === 'custom';
        $this->item_name = $item['item_name'];
        $this->item_description = $item['description'] ?? '';
        $this->item_quantity = $item['quantity'];
        $this->item_unit = $item['unit'];
        $this->item_unit_price = $item['unit_price'];
        $this->item_total = $item['total_amount'];

        if ($item['budget_item_id']) {
            $budgetItem = BudgetItem::find($item['budget_item_id']);
            $this->budgetItemSearch = $budgetItem ? $budgetItem->code . ' - ' . $budgetItem->name : '';
        }

        if ($item['catalog_item_id']) {
            $this->catalogItemSearch = CatalogItem::find($item['catalog_item_id'])?->name ?? '';
        }

        $this->showItemModal = true;
    }

    public function closeItemModal()
    {
        $this->showItemModal = false;
        $this->resetItemForm();
    }

    protected function resetItemForm()
    {
        $this->item_budget_item_id = null;
        $this->budgetItemSearch = '';
        $this->item_catalog_item_id = null;
        $this->catalogItemSearch = '';
        $this->item_is_custom = true;
        $this->item_name = '';
        $this->item_description = '';
        $this->item_quantity = 1;
        $this->item_unit = '';
        $this->item_unit_price = '';
        $this->item_total = 0;
        $this->editingItemIndex = null;
    }

    // =========================================================================
    // COST CODE AND CATALOG SEARCH
    // =========================================================================

    public function selectBudgetItem($budgetItemId)
    {
        $budgetItem = BudgetItem::find($budgetItemId);

        if ($budgetItem) {
            $this->item_budget_item_id = $budgetItemId;
            $this->budgetItemSearch = $budgetItem->code . ' - ' . $budgetItem->name;
        }
    }

    public function clearBudgetItem()
    {
        $this->item_budget_item_id = null;
        $this->budgetItemSearch = '';
    }

    public function selectCatalogItem($catalogItemId)
    {
        $catalogItem = CatalogItem::find($catalogItemId);

        if ($catalogItem) {
            $this->item_catalog_item_id = $catalogItemId;
            $this->catalogItemSearch = $catalogItem->name;
            $this->item_name = $catalogItem->name;
            $this->item_unit = $catalogItem->usage_unit ?? $catalogItem->purchase_unit ?? '';
            $this->item_unit_price = $catalogItem->unit_cost ?? $catalogItem->current_cost ?? 0;
            $this->item_is_custom = false;
            $this->calculateItemTotal();
        }
    }

    public function clearCatalogItem()
    {
        $this->item_catalog_item_id = null;
        $this->catalogItemSearch = '';
        $this->item_is_custom = true;
    }

    public function toggleCustomItem()
    {
        $this->item_is_custom = ! $this->item_is_custom;

        if ($this->item_is_custom) {
            $this->clearCatalogItem();
        }
    }

    // =========================================================================
    // TOTALS
    // =========================================================================

    public function calculateItemTotal()
    {
        $qty = floatval($this->item_quantity) ?: 0;
        $price = floatval($this->item_unit_price) ?: 0;
        $this->item_total = round($qty * $price, 2);
    }

    public function updatedItemQuantity()
    {
        $this->calculateItemTotal();
    }

    public function updatedItemUnitPrice()
    {
        $this->calculateItemTotal();
    }

    public function calculateExpenseTotal()
    {
        $total = 0;

        foreach ($this->items as $item) {
            $total += floatval($item['total_amount'] ?? 0);
        }

        $this->expense_total_amount = round($total, 2);
    }

    public function saveItem()
    {
        $this->validate([
            'item_name' => 'required|string|max:255',
            'item_quantity' => 'required|numeric|min:0.01',
            'item_unit_price' => 'required|numeric|min:0',
        ], [
            'item_name.required' => __('Item name is required.'),
            'item_quantity.required' => __('Quantity is required.'),
            'item_unit_price.required' => __('Unit price is required.'),
        ]);

        $this->calculateItemTotal();

        $existing = $this->editingItemIndex !== null ? ($this->items[$this->editingItemIndex] ?? null) : null;

        $itemData = [
            'budget_item_id' => $this->item_budget_item_id,
            // Carried on the line so the table can show it without a query per row.
            'cost_code' => $this->item_budget_item_id ? $this->budgetItemSearch : null,
            'catalog_item_id' => $this->item_catalog_item_id,
            'item_name' => $this->item_name,
            'item_type' => $this->item_is_custom ? 'custom' : 'catalog',
            'description' => $this->item_description,
            'quantity' => $this->item_quantity,
            'unit' => $this->item_unit,
            'unit_price' => $this->item_unit_price,
            'total_amount' => $this->item_total,
        ];

        // Locked amounts keep what was actually spent; only the coding changes.
        if ($existing && $this->amountsAreLocked()) {
            $itemData['quantity'] = $existing['quantity'];
            $itemData['unit_price'] = $existing['unit_price'];
            $itemData['total_amount'] = $existing['total_amount'];
        }

        if ($this->editingItemIndex !== null) {
            $this->items[$this->editingItemIndex] = $itemData;
        } else {
            $this->items[] = $itemData;
        }

        $this->calculateExpenseTotal();
        $this->closeItemModal();
    }

    public function removeItem($index)
    {
        if ($this->amountsAreLocked()) {
            return;
        }

        unset($this->items[$index]);
        $this->items = array_values($this->items);
        $this->calculateExpenseTotal();
    }

    // =========================================================================
    // VALIDATION
    // =========================================================================

    /** Take the chosen receipt back off before the record is saved. */
    public function clearExpenseReceipt()
    {
        // Livewire's own `_removeUpload()` deletes the temporary file; dropping
        // only the reference would leave it in livewire-tmp until the daily
        // sweep.
        $this->expense_receipt?->delete();

        $this->expense_receipt = null;
    }

    protected function validateExpenseForm(): void
    {
        $this->validate([
            'expense_date' => 'required|date',
            'expense_supplier_id' => 'nullable|exists:vendors,id,is_supplier,1',
            'expense_job_site_id' => [
                'nullable',
                // A job site of THIS project and no other. Without the
                // project_id clause the picker accepted any id in the table.
                Rule::exists('job_sites', 'id')->where('project_id', $this->expenseProjectId()),
            ],
            'expense_receipt' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'items' => 'required|array|min:1',
        ], [
            'items.required' => __('At least one item is required.'),
            'items.min' => __('At least one item is required.'),
        ]);

        if ($this->expense_has_installments) {
            $this->validate([
                'expense_total_installments' => 'required|integer|min:2|max:120',
                'expense_payment_frequency' => 'required|in:weekly,biweekly,monthly',
                'expense_payment_due_date' => 'required|date',
            ]);

            return;
        }

        $this->validate([
            'expense_status' => 'required|in:unpaid,paid',
        ]);

        if ($this->expense_status === 'unpaid') {
            $this->validate([
                'expense_payment_due_date' => 'required|date',
            ]);
        }
    }

    // =========================================================================
    // WHERE THE EXPENSE LANDS
    // =========================================================================

    /**
     * The project or job site the form is currently pointing at.
     *
     * The Location picker can move an expense between the project and any of
     * its job sites, so the permission question has to be asked about the
     * *destination* and not only about the screen it was opened from. A person
     * holding `expenses.create` on one job site alone may not file against a
     * sibling site by changing the dropdown.
     */
    protected function expenseDestination(): JobSite|Project|null
    {
        if ($this->expense_job_site_id) {
            return JobSite::where('project_id', $this->expenseProjectId())
                ->find($this->expense_job_site_id);
        }

        return Project::find($this->expenseProjectId());
    }

    /**
     * The job sites this person may actually put an expense on, which is what
     * the Location picker offers. An empty list still leaves "Project
     * (General)" where they hold the ability on the project itself.
     */
    protected function selectableJobSites(string $ability): Collection
    {
        $resolver = app(\App\Services\PermissionResolver::class);

        return JobSite::where('project_id', $this->expenseProjectId())
            ->orderBy('job_site_name')
            ->get()
            ->filter(fn (JobSite $site) => $resolver->allows(Auth::user(), $ability, $site))
            ->values();
    }

    // =========================================================================
    // PERSISTENCE
    // =========================================================================

    /**
     * The header values the form describes, ready for create or update.
     */
    protected function expenseHeaderData(): array
    {
        $data = [
            'project_id' => $this->expenseProjectId(),
            'job_site_id' => $this->expense_job_site_id ?: null,
            'supplier_id' => $this->expense_supplier_id ?: null,
            'expense_date' => $this->expense_date,
            'notes' => $this->expense_notes,
            'total_amount' => $this->expense_total_amount,
            'payment_method' => $this->expense_payment_method,
            'is_auto_payment' => $this->expense_is_auto_payment,
        ];

        if ($this->expense_has_installments) {
            $data['status'] = 'unpaid';
            $data['total_installments'] = $this->expense_total_installments;
            $data['payment_frequency'] = $this->expense_payment_frequency;
            $data['payment_due_date'] = $this->expense_payment_due_date;

            return $data;
        }

        $data['status'] = $this->expense_status;
        $data['total_installments'] = 1;

        if ($this->expense_status === 'paid') {
            $data['paid_date'] = $this->expense_paid_date ?: now()->format('Y-m-d');
            $data['payment_due_date'] = null;
        } else {
            $data['payment_due_date'] = $this->expense_payment_due_date;
            $data['paid_date'] = null;
        }

        return $data;
    }

    /**
     * Write the line items, giving every uncoded line the budget's default
     * cost code so nothing is invisible on the budget screens.
     */
    protected function syncExpenseItems(Expense $expense): void
    {
        $expense->items()->delete();

        foreach (array_values($this->items) as $index => $item) {
            $budgetItemId = $item['budget_item_id'];

            if (! $budgetItemId) {
                $budgetItemId = BudgetService::getDefaultItem(
                    $this->expenseProjectId(),
                    $this->expense_job_site_id ?: null,
                    $expense->created_by ?? Auth::id()
                )->id;
            }

            $expense->items()->create([
                'budget_item_id' => $budgetItemId,
                'catalog_item_id' => $item['catalog_item_id'],
                'item_name' => $item['item_name'],
                'item_type' => $item['item_type'],
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'unit' => $item['unit'],
                'unit_price' => $item['unit_price'],
                'total_amount' => $item['total_amount'],
                'sort_order' => $index,
            ]);
        }
    }

    // =========================================================================
    // SEARCH RESULTS FOR THE VIEW
    // =========================================================================

    protected function supplierSearchResults(): Collection
    {
        if (! $this->supplierSearch || strlen($this->supplierSearch) < 2 || $this->expense_supplier_id) {
            return collect();
        }

        return Supplier::where('name', 'like', '%' . $this->supplierSearch . '%')->take(10)->get();
    }

    protected function budgetItemSearchResults(): Collection
    {
        if (! $this->budgetItemSearch || $this->item_budget_item_id) {
            return collect();
        }

        $budget = Budget::where('project_id', $this->expenseProjectId())
            ->where('job_site_id', $this->expense_job_site_id ?: null)
            ->first();

        if (! $budget) {
            return collect();
        }

        return BudgetItem::where('budget_id', $budget->id)
            ->where(function ($q) {
                $q->where('code', 'like', '%' . $this->budgetItemSearch . '%')
                    ->orWhere('name', 'like', '%' . $this->budgetItemSearch . '%');
            })
            ->orderBy('sort_order')
            ->take(15)
            ->get();
    }

    protected function catalogItemSearchResults(): Collection
    {
        if (! $this->catalogItemSearch || strlen($this->catalogItemSearch) < 2 || $this->item_catalog_item_id) {
            return collect();
        }

        return CatalogItem::where('is_active', true)
            ->where('name', 'like', '%' . $this->catalogItemSearch . '%')
            ->take(10)
            ->get();
    }
}
