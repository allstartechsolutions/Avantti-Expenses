<?php

namespace App\Livewire\PurchaseOrder;

use App\Livewire\Concerns\AuthorizesAbility;
use App\Models\BudgetItem;
use App\Models\CatalogItem;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\BudgetService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

class PurchaseOrderEdit extends Component
{
    use AuthorizesAbility;

    use WithFileUploads;

    public PurchaseOrder $purchaseOrder;

    // Header fields
    public $po_job_site_id = null;
    public $po_supplier_id = null;
    public $supplierSearch = '';
    public $po_number = '';
    public $po_date;
    public $po_notes = '';
    public $po_receipt = null;
    public $existingReceiptPath = null;

    // Payment fields
    public $po_payment_method = null;
    public $po_is_auto_payment = false;
    public $po_has_installments = false;
    public $po_total_installments = 2;
    public $po_payment_frequency = 'monthly';
    public $po_payment_due_date;

    // Items
    public $items = [];
    public $po_total_amount = 0;

    /**
     * Freight, tax and discount come from a quotation award and live on the
     * header, in cents. They are not editable here, but they must survive the
     * save — recomputing the total from the items alone used to drop them.
     */
    public $po_freight_amount = 0;
    public $po_tax_amount = 0;
    public $po_discount_amount = 0;

    /** The priced lines on their own, for the summary. */
    public $po_items_amount = 0;

    // Item Modal
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

    public function mount(PurchaseOrder $purchaseOrder)
    {
        $this->authorizeAbility('purchase-orders.edit', $purchaseOrder);

        // Only draft and rejected POs can be edited
        if (!$purchaseOrder->canBeEdited()) {
            session()->flash('error', __('This purchase order cannot be edited.'));
            return redirect()->route('purchase-orders.show', $purchaseOrder->id);
        }

        $this->purchaseOrder = $purchaseOrder->load(['project', 'jobSite', 'supplier', 'items']);

        // Load existing data
        $this->po_job_site_id = $purchaseOrder->job_site_id;
        $this->po_supplier_id = $purchaseOrder->supplier_id;
        $this->supplierSearch = $purchaseOrder->supplier?->name ?? '';
        $this->po_number = $purchaseOrder->po_number ?? '';
        $this->po_date = $purchaseOrder->po_date->format('Y-m-d');
        $this->po_notes = $purchaseOrder->notes ?? '';
        $this->existingReceiptPath = $purchaseOrder->receipt_path;

        // Payment fields
        $this->po_payment_method = $purchaseOrder->payment_method;
        $this->po_is_auto_payment = $purchaseOrder->is_auto_payment;
        $this->po_has_installments = $purchaseOrder->total_installments > 1;
        $this->po_total_installments = $purchaseOrder->total_installments > 1 ? $purchaseOrder->total_installments : 2;
        $this->po_payment_frequency = $purchaseOrder->payment_frequency ?? 'monthly';
        $this->po_payment_due_date = $purchaseOrder->payment_due_date?->format('Y-m-d') ?? now()->format('Y-m-d');

        // Load items
        foreach ($purchaseOrder->items as $item) {
            $this->items[] = [
                'id' => $item->id,
                'budget_item_id' => $item->budget_item_id,
                'catalog_item_id' => $item->catalog_item_id,
                'item_name' => $item->item_name,
                'item_type' => $item->item_type,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit' => $item->unit,
                'unit_price' => $item->unit_price,
                'total_amount' => $item->total_amount,
            ];
        }

        $this->po_freight_amount = (int) $this->purchaseOrder->freight_amount;
        $this->po_tax_amount = (int) $this->purchaseOrder->tax_amount;
        $this->po_discount_amount = (int) $this->purchaseOrder->discount_amount;

        $this->calculatePOTotal();
    }

    // Supplier methods
    public function selectSupplier($supplierId)
    {
        $supplier = Supplier::find($supplierId);
        if ($supplier) {
            $this->po_supplier_id = $supplierId;
            $this->supplierSearch = $supplier->name;
        }
    }

    public function clearSupplier()
    {
        $this->po_supplier_id = null;
        $this->supplierSearch = '';
    }

    // Item Modal methods
    public function openAddItemModal()
    {
        $this->resetItemForm();
        $this->editingItemIndex = null;
        $this->showItemModal = true;
    }

    public function openEditItemModal($index)
    {
        $item = $this->items[$index];

        $this->editingItemIndex = $index;
        $this->item_budget_item_id = $item['budget_item_id'];
        $this->item_catalog_item_id = $item['catalog_item_id'];
        $this->item_is_custom = $item['item_type'] === 'custom';
        $this->item_name = $item['item_name'];
        $this->item_description = $item['description'] ?? '';
        $this->item_quantity = $item['quantity'];
        $this->item_unit = $item['unit'];
        $this->item_unit_price = $item['unit_price'];
        $this->item_total = $item['total_amount'];

        // Set search fields
        if ($item['budget_item_id']) {
            $budgetItem = BudgetItem::find($item['budget_item_id']);
            $this->budgetItemSearch = $budgetItem ? $budgetItem->code . ' - ' . $budgetItem->name : '';
        }

        if ($item['catalog_item_id']) {
            $catalogItem = CatalogItem::find($item['catalog_item_id']);
            $this->catalogItemSearch = $catalogItem?->name ?? '';
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

    // Cost code search
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

    // Catalog item search
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
        $this->item_is_custom = !$this->item_is_custom;
        if ($this->item_is_custom) {
            $this->clearCatalogItem();
        }
    }

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

        $itemData = [
            'id' => $this->editingItemIndex !== null ? ($this->items[$this->editingItemIndex]['id'] ?? null) : null,
            'budget_item_id' => $this->item_budget_item_id,
            'catalog_item_id' => $this->item_catalog_item_id,
            'item_name' => $this->item_name,
            'item_type' => $this->item_is_custom ? 'custom' : 'catalog',
            'description' => $this->item_description,
            'quantity' => $this->item_quantity,
            'unit' => $this->item_unit,
            'unit_price' => $this->item_unit_price,
            'total_amount' => $this->item_total,
        ];

        if ($this->editingItemIndex !== null) {
            $this->items[$this->editingItemIndex] = $itemData;
        } else {
            $this->items[] = $itemData;
        }

        $this->calculatePOTotal();
        $this->closeItemModal();
    }

    public function removeItem($index)
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
        $this->calculatePOTotal();
    }

    public function calculatePOTotal()
    {
        $total = 0;
        foreach ($this->items as $item) {
            $total += floatval($item['total_amount'] ?? 0);
        }

        $this->po_items_amount = round($total, 2);

        // The header extras are part of what this order is worth. Leaving them
        // out here is what silently deleted a quotation's freight the first
        // time anyone saved the purchase order it produced.
        $this->po_total_amount = max(0, round(
            $total
            + floatval($this->po_freight_amount)
            + floatval($this->po_tax_amount)
            - floatval($this->po_discount_amount),
            2
        ));
    }

    public function removeExistingReceipt()
    {
        $this->existingReceiptPath = null;
    }

    /**
     * Save changes
     */
    public function save()
    {
        $this->authorizeAbility('purchase-orders.edit', $this->purchaseOrder);

        // Re-check if still editable
        if (!$this->purchaseOrder->canBeEdited()) {
            session()->flash('error', __('This purchase order can no longer be edited.'));
            return redirect()->route('purchase-orders.show', $this->purchaseOrder->id);
        }

        $this->validate([
            'po_date' => 'required|date',
            'po_supplier_id' => 'nullable|exists:vendors,id,is_supplier,1',
            'po_job_site_id' => 'nullable|exists:job_sites,id',
            'po_receipt' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
            'items' => 'required|array|min:1',
        ], [
            'items.required' => __('At least one item is required.'),
            'items.min' => __('At least one item is required.'),
        ]);

        // Payment validation (if installments enabled)
        if ($this->po_has_installments) {
            $this->validate([
                'po_total_installments' => 'required|integer|min:2|max:120',
                'po_payment_frequency' => 'required|in:weekly,biweekly,monthly',
                'po_payment_due_date' => 'required|date',
            ]);
        }

        // Handle receipt upload
        $receiptPath = $this->existingReceiptPath;
        if ($this->po_receipt) {
            // Delete old receipt if exists
            if ($this->purchaseOrder->receipt_path && Storage::exists($this->purchaseOrder->receipt_path)) {
                Storage::delete($this->purchaseOrder->receipt_path);
            }
            $receiptPath = $this->po_receipt->store('purchase-orders', 'local');
        } elseif ($this->existingReceiptPath === null && $this->purchaseOrder->receipt_path) {
            // User removed the existing receipt
            if (Storage::exists($this->purchaseOrder->receipt_path)) {
                Storage::delete($this->purchaseOrder->receipt_path);
            }
            $receiptPath = null;
        }

        DB::transaction(function () use ($receiptPath) {
            // Update PO header
            $this->purchaseOrder->update([
                'job_site_id' => $this->po_job_site_id ?: null,
                'supplier_id' => $this->po_supplier_id ?: null,
                'po_number' => $this->po_number ?: null,
                'po_date' => $this->po_date,
                'notes' => $this->po_notes,
                'receipt_path' => $receiptPath,
                'total_amount' => $this->po_total_amount,
                'freight_amount' => $this->po_freight_amount,
                'tax_amount' => $this->po_tax_amount,
                'discount_amount' => $this->po_discount_amount,
                'payment_method' => $this->po_payment_method,
                'is_auto_payment' => $this->po_is_auto_payment,
                'total_installments' => $this->po_has_installments ? $this->po_total_installments : 1,
                'payment_frequency' => $this->po_has_installments ? $this->po_payment_frequency : null,
                'payment_due_date' => $this->po_payment_due_date ?: null,
            ]);

            // Get existing item IDs
            $existingItemIds = $this->purchaseOrder->items()->pluck('id')->toArray();
            $updatedItemIds = [];

            // Update or create items
            foreach ($this->items as $index => $item) {
                $budgetItemId = $item['budget_item_id'];

                // Auto-assign to the budget default bucket if no cost code
                if (!$budgetItemId) {
                    $defaultItem = BudgetService::getDefaultItem(
                        $this->purchaseOrder->project_id,
                        $this->po_job_site_id,
                        Auth::id()
                    );
                    $budgetItemId = $defaultItem->id;
                }

                $itemData = [
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
                ];

                if (isset($item['id']) && $item['id']) {
                    // Update existing item
                    $this->purchaseOrder->items()->where('id', $item['id'])->update($itemData);
                    $updatedItemIds[] = $item['id'];
                } else {
                    // Create new item
                    $newItem = $this->purchaseOrder->items()->create($itemData);
                    $updatedItemIds[] = $newItem->id;
                }
            }

            // Delete items that were removed
            $itemsToDelete = array_diff($existingItemIds, $updatedItemIds);
            if (!empty($itemsToDelete)) {
                $this->purchaseOrder->items()->whereIn('id', $itemsToDelete)->delete();
            }
        });

        session()->flash('message', __('Purchase order updated successfully!'));

        return redirect()->route('purchase-orders.show', $this->purchaseOrder->id);
    }

    public function render()
    {
        // Suppliers search
        $suppliers = collect();
        if ($this->supplierSearch && strlen($this->supplierSearch) >= 2 && !$this->po_supplier_id) {
            $suppliers = Supplier::where('name', 'like', '%' . $this->supplierSearch . '%')
                ->take(10)
                ->get();
        }

        // Budget items search (cost codes)
        $budgetItems = collect();
        if ($this->budgetItemSearch && strlen($this->budgetItemSearch) >= 1 && !$this->item_budget_item_id) {
            // Get budget for current location
            $budget = \App\Models\Budget::where('project_id', $this->purchaseOrder->project_id)
                ->where('job_site_id', $this->po_job_site_id)
                ->first();

            if ($budget) {
                $budgetItems = BudgetItem::where('budget_id', $budget->id)
                    ->where(function ($q) {
                        $q->where('code', 'like', '%' . $this->budgetItemSearch . '%')
                            ->orWhere('name', 'like', '%' . $this->budgetItemSearch . '%');
                    })
                    ->orderBy('sort_order')
                    ->take(15)
                    ->get();
            }
        }

        // Catalog items search
        $catalogItems = collect();
        if ($this->catalogItemSearch && strlen($this->catalogItemSearch) >= 2 && !$this->item_catalog_item_id) {
            $catalogItems = CatalogItem::where('is_active', true)
                ->where('name', 'like', '%' . $this->catalogItemSearch . '%')
                ->take(10)
                ->get();
        }

        // Job sites for location dropdown
        $jobSites = $this->purchaseOrder->project->jobSites()->orderBy('job_site_name')->get();

        return view('livewire.purchase-order.purchase-order-edit', [
            'suppliers' => $suppliers,
            'budgetItems' => $budgetItems,
            'catalogItems' => $catalogItems,
            'jobSites' => $jobSites,
        ])->layout('components.layouts.app');
    }
}
