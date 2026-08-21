<?php

namespace App\Livewire\Estimate;

use App\Livewire\Concerns\AuthorizesAbility;
use App\Models\CatalogItem;
use App\Models\Client;
use App\Models\DocumentMessage;
use App\Models\Estimate;
use App\Models\JobSite;
use App\Models\Project;
use App\Models\TaxRate;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;

class EstimateEdit extends Component
{
    use AuthorizesAbility;

    public Estimate $estimate;

    // Header fields
    public $client_id = null;
    public $clientSearch = '';
    public $project_id = null;
    public $projectSearch = '';
    public $job_site_id = null;
    public $jobSiteSearch = '';
    public $estimate_number = '';
    public $estimate_date;
    public $terms = 'net_30';
    public $due_date;

    // Items
    public $items = [];

    // Overall discount
    public $overall_discount_type = null;
    public $overall_discount_value = null;

    // Message
    public $selectedMessageId = null;
    public $message_title = '';
    public $message_body = '';

    // Notes
    public $notes = '';

    // Calculated totals
    public $calc_subtotal = 0;
    public $calc_discount_amount = 0;
    public $calc_tax_total = 0;
    public $calc_total = 0;

    // Item Modal
    public $showItemModal = false;
    public $editingItemIndex = null;

    // Item form fields
    public $item_catalog_item_id = null;
    public $catalogItemSearch = '';
    public $item_is_custom = true;
    public $item_name = '';
    public $item_description = '';
    public $item_quantity = 1;
    public $item_unit = 'unit';
    public $item_unit_price = '';
    public $item_discount_type = null;
    public $item_discount_value = null;
    public $item_is_taxable = false;
    public $item_tax_rate = 0;

    // Item calculated
    public $item_calc_subtotal = 0;
    public $item_calc_discount = 0;
    public $item_calc_tax = 0;
    public $item_calc_total = 0;

    public function mount(Estimate $estimate)
    {
        $this->authorizeAbility('estimates.edit');

        if (!$estimate->canBeEdited()) {
            return redirect()->route('estimates.show', $estimate);
        }

        $this->estimate = $estimate->load(['client', 'project', 'jobSite', 'items']);

        // Populate header fields
        $this->client_id = $estimate->client_id;
        $this->clientSearch = $estimate->client->company_name;
        $this->project_id = $estimate->project_id;
        $this->projectSearch = $estimate->project?->project_name ?? '';
        $this->job_site_id = $estimate->job_site_id;
        $this->jobSiteSearch = $estimate->jobSite?->job_site_name ?? '';
        $this->estimate_number = $estimate->estimate_number;
        $this->estimate_date = $estimate->estimate_date->format('Y-m-d');
        $this->terms = $estimate->terms;
        $this->due_date = $estimate->due_date->format('Y-m-d');

        // Populate overall discount
        $this->overall_discount_type = $estimate->discount_type;
        $this->overall_discount_value = $estimate->discount_value;

        // Populate message
        $this->message_title = $estimate->message_title ?? '';
        $this->message_body = $estimate->message_body ?? '';

        // Populate notes
        $this->notes = $estimate->notes ?? '';

        // Populate items from DB
        foreach ($estimate->items as $item) {
            $lineSubtotal = round($item->quantity * $item->unit_price, 2);
            $lineNet = max($lineSubtotal - $item->discount_amount, 0);

            $this->items[] = [
                'catalog_item_id' => $item->catalog_item_id,
                'item_type' => $item->item_type,
                'item_name' => $item->item_name,
                'description' => $item->description,
                'quantity' => $item->quantity,
                'unit' => $item->unit,
                'unit_price' => $item->unit_price,
                'discount_type' => $item->discount_type,
                'discount_value' => $item->discount_value,
                'discount_amount' => $item->discount_amount,
                'is_taxable' => $item->is_taxable,
                'tax_rate' => $item->tax_rate,
                'tax_amount' => $item->tax_amount,
                'line_net' => $lineNet,
                'total_amount' => $item->total_amount,
            ];
        }

        // Calculate totals from loaded items
        $this->calculateEstimateTotals();
    }

    // --- Quick Add Client ---

    public function openQuickAddClient()
    {
        $this->dispatch('open-quick-add-client', companyName: $this->clientSearch);
    }

    #[On('client-quick-created')]
    public function onClientQuickCreated($clientId)
    {
        $this->selectClient($clientId);
    }

    // --- Client/Project/JobSite cascading ---

    public function selectClient($clientId)
    {
        $client = Client::find($clientId);
        if ($client) {
            $this->client_id = $clientId;
            $this->clientSearch = $client->company_name;
            $this->clearProject();
        }
    }

    public function clearClient()
    {
        $this->client_id = null;
        $this->clientSearch = '';
        $this->clearProject();
    }

    public function selectProject($projectId)
    {
        $project = Project::find($projectId);
        if ($project) {
            $this->project_id = $projectId;
            $this->projectSearch = $project->project_name;
            $this->clearJobSite();
        }
    }

    public function clearProject()
    {
        $this->project_id = null;
        $this->projectSearch = '';
        $this->clearJobSite();
    }

    public function selectJobSite($jobSiteId)
    {
        $jobSite = JobSite::find($jobSiteId);
        if ($jobSite) {
            $this->job_site_id = $jobSiteId;
            $this->jobSiteSearch = $jobSite->job_site_name;
        }
    }

    public function clearJobSite()
    {
        $this->job_site_id = null;
        $this->jobSiteSearch = '';
    }

    // --- Date/Terms ---

    public function updatedEstimateDate()
    {
        $this->calculateDueDate();
    }

    public function updatedTerms()
    {
        $this->calculateDueDate();
    }

    protected function calculateDueDate()
    {
        if ($this->estimate_date && $this->terms) {
            $this->due_date = Estimate::calculateDueDate($this->estimate_date, $this->terms);
        }
    }

    // --- Message ---

    public function updatedSelectedMessageId($value)
    {
        if ($value) {
            $message = DocumentMessage::find($value);
            if ($message) {
                $this->message_title = $message->title;
                $this->message_body = $message->body;
            }
        } else {
            $this->message_title = '';
            $this->message_body = '';
        }
    }

    // --- Item Modal ---

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
        $this->item_catalog_item_id = $item['catalog_item_id'];
        $this->item_is_custom = $item['item_type'] === 'custom';
        $this->item_name = $item['item_name'];
        $this->item_description = $item['description'] ?? '';
        $this->item_quantity = $item['quantity'];
        $this->item_unit = $item['unit'] ?? '';
        $this->item_unit_price = $item['unit_price'];
        $this->item_discount_type = $item['discount_type'];
        $this->item_discount_value = $item['discount_value'];
        $this->item_is_taxable = $item['is_taxable'];
        $this->item_tax_rate = $item['tax_rate'];

        if ($item['catalog_item_id']) {
            $catalogItem = CatalogItem::find($item['catalog_item_id']);
            $this->catalogItemSearch = $catalogItem?->name ?? '';
        }

        $this->calculateItemTotals();
        $this->showItemModal = true;
    }

    public function closeItemModal()
    {
        $this->showItemModal = false;
        $this->resetItemForm();
    }

    protected function resetItemForm()
    {
        $this->item_catalog_item_id = null;
        $this->catalogItemSearch = '';
        $this->item_is_custom = true;
        $this->item_name = '';
        $this->item_description = '';
        $this->item_quantity = 1;
        $this->item_unit = 'unit';
        $this->item_unit_price = '';
        $this->item_discount_type = null;
        $this->item_discount_value = null;
        $this->item_is_taxable = false;
        $this->item_tax_rate = 0;
        $this->item_calc_subtotal = 0;
        $this->item_calc_discount = 0;
        $this->item_calc_tax = 0;
        $this->item_calc_total = 0;
        $this->editingItemIndex = null;
    }

    // --- Catalog item ---

    public function selectCatalogItem($catalogItemId)
    {
        $catalogItem = CatalogItem::with('taxRate')->find($catalogItemId);
        if ($catalogItem) {
            $this->item_catalog_item_id = $catalogItemId;
            $this->catalogItemSearch = $catalogItem->name;
            $this->item_name = $catalogItem->name;
            $this->item_description = $catalogItem->description ?? '';
            $this->item_unit = $catalogItem->usage_unit ?? $catalogItem->purchase_unit ?? '';
            $this->item_unit_price = $catalogItem->unit_cost ?? $catalogItem->current_cost ?? 0;
            $this->item_is_custom = false;
            $this->item_is_taxable = (bool) $catalogItem->is_taxable;
            $this->item_tax_rate = $catalogItem->taxRate?->rate ?? 0;
            $this->calculateItemTotals();
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

    // --- Item calculations ---

    public function calculateItemTotals()
    {
        $qty = floatval($this->item_quantity) ?: 0;
        $price = floatval($this->item_unit_price) ?: 0;
        $this->item_calc_subtotal = round($qty * $price, 2);

        $discountValue = floatval($this->item_discount_value) ?: 0;
        if ($this->item_discount_type === 'percentage') {
            $this->item_calc_discount = round($this->item_calc_subtotal * ($discountValue / 100), 2);
        } elseif ($this->item_discount_type === 'fixed') {
            $this->item_calc_discount = round($discountValue, 2);
        } else {
            $this->item_calc_discount = 0;
        }

        $lineNet = max($this->item_calc_subtotal - $this->item_calc_discount, 0);

        $taxRate = floatval($this->item_tax_rate) ?: 0;
        $this->item_calc_tax = $this->item_is_taxable ? round($lineNet * $taxRate, 2) : 0;

        $this->item_calc_total = round($lineNet + $this->item_calc_tax, 2);
    }

    public function updatedItemQuantity() { $this->calculateItemTotals(); }
    public function updatedItemUnitPrice() { $this->calculateItemTotals(); }
    public function updatedItemDiscountType() { $this->calculateItemTotals(); }
    public function updatedItemDiscountValue() { $this->calculateItemTotals(); }
    public function updatedItemTaxRate() { $this->calculateItemTotals(); }

    public function updatedItemIsTaxable()
    {
        if (!$this->item_is_taxable) {
            $this->item_tax_rate = 0;
        } else {
            if (floatval($this->item_tax_rate) == 0) {
                $default = TaxRate::where('is_default', true)->first();
                $this->item_tax_rate = $default?->rate ?? 0;
            }
        }
        $this->calculateItemTotals();
    }

    // --- Save/Remove item ---

    public function saveItem()
    {
        $this->validate([
            'item_name' => 'required|string|max:255',
            'item_quantity' => 'required|numeric|min:0.01',
            'item_unit_price' => 'required|numeric|min:0',
        ], [
            'item_name.required' => 'Item name is required.',
            'item_quantity.required' => 'Quantity is required.',
            'item_quantity.min' => 'Quantity must be greater than zero.',
            'item_unit_price.required' => 'Unit price is required.',
        ]);

        $this->calculateItemTotals();

        $lineNet = max($this->item_calc_subtotal - $this->item_calc_discount, 0);

        $itemData = [
            'catalog_item_id' => $this->item_catalog_item_id,
            'item_type' => $this->item_is_custom ? 'custom' : 'catalog',
            'item_name' => $this->item_name,
            'description' => $this->item_description,
            'quantity' => $this->item_quantity,
            'unit' => $this->item_unit,
            'unit_price' => $this->item_unit_price,
            'discount_type' => $this->item_discount_type,
            'discount_value' => $this->item_discount_value,
            'discount_amount' => $this->item_calc_discount,
            'is_taxable' => $this->item_is_taxable,
            'tax_rate' => $this->item_tax_rate,
            'tax_amount' => $this->item_calc_tax,
            'line_net' => $lineNet,
            'total_amount' => $this->item_calc_total,
        ];

        if ($this->editingItemIndex !== null) {
            $this->items[$this->editingItemIndex] = $itemData;
        } else {
            $this->items[] = $itemData;
        }

        $this->calculateEstimateTotals();
        $this->closeItemModal();
    }

    public function removeItem($index)
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
        $this->calculateEstimateTotals();
    }

    // --- Overall discount ---

    public function updatedOverallDiscountType() { $this->calculateEstimateTotals(); }
    public function updatedOverallDiscountValue() { $this->calculateEstimateTotals(); }

    // --- Estimate totals ---

    public function calculateEstimateTotals()
    {
        $subtotal = 0;
        $taxTotal = 0;

        foreach ($this->items as $item) {
            $subtotal += floatval($item['line_net'] ?? 0);
            $taxTotal += floatval($item['tax_amount'] ?? 0);
        }

        $this->calc_subtotal = round($subtotal, 2);
        $this->calc_tax_total = round($taxTotal, 2);

        $discountValue = floatval($this->overall_discount_value) ?: 0;
        if ($this->overall_discount_type === 'percentage') {
            $this->calc_discount_amount = round($this->calc_subtotal * ($discountValue / 100), 2);
        } elseif ($this->overall_discount_type === 'fixed') {
            $this->calc_discount_amount = round($discountValue, 2);
        } else {
            $this->calc_discount_amount = 0;
        }

        $this->calc_total = round($this->calc_subtotal - $this->calc_discount_amount + $this->calc_tax_total, 2);
    }

    // --- Save ---

    public function saveEstimate()
    {
        $this->authorizeAbility('estimates.edit');

        $this->validate([
            'client_id' => 'required|exists:clients,id',
            'estimate_date' => 'required|date',
            'terms' => 'required|in:due_upon_receipt,net_15,net_30,net_60,net_90',
            'items' => 'required|array|min:1',
        ], [
            'client_id.required' => 'Please select a client.',
            'items.required' => 'At least one item is required.',
            'items.min' => 'At least one item is required.',
        ]);

        $this->calculateEstimateTotals();

        DB::transaction(function () {
            $this->estimate->update([
                'client_id' => $this->client_id,
                'project_id' => $this->project_id ?: null,
                'job_site_id' => $this->job_site_id ?: null,
                'estimate_date' => $this->estimate_date,
                'terms' => $this->terms,
                'due_date' => $this->due_date,
                'message_title' => $this->message_title ?: null,
                'message_body' => $this->message_body ?: null,
                'discount_type' => $this->overall_discount_type,
                'discount_value' => $this->overall_discount_value,
                'discount_amount' => $this->calc_discount_amount,
                'subtotal' => $this->calc_subtotal,
                'tax_total' => $this->calc_tax_total,
                'total_amount' => $this->calc_total,
                'notes' => $this->notes ?: null,
            ]);

            // Delete old items and recreate
            $this->estimate->items()->delete();

            foreach ($this->items as $index => $item) {
                $this->estimate->items()->create([
                    'catalog_item_id' => $item['catalog_item_id'],
                    'item_type' => $item['item_type'],
                    'item_name' => $item['item_name'],
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit' => $item['unit'],
                    'unit_price' => $item['unit_price'],
                    'discount_type' => $item['discount_type'],
                    'discount_value' => $item['discount_value'],
                    'discount_amount' => $item['discount_amount'],
                    'is_taxable' => $item['is_taxable'],
                    'tax_rate' => $item['tax_rate'],
                    'tax_amount' => $item['tax_amount'],
                    'total_amount' => $item['total_amount'],
                    'sort_order' => $index,
                ]);
            }
        });

        session()->flash('message', __('Estimate updated successfully!'));

        return redirect()->route('estimates.show', $this->estimate->id);
    }

    // --- Render ---

    public function render()
    {
        $clients = collect();
        if ($this->clientSearch && strlen($this->clientSearch) >= 2 && !$this->client_id) {
            $clients = Client::where('company_name', 'like', '%' . $this->clientSearch . '%')
                ->take(10)
                ->get();
        }

        $projects = collect();
        if ($this->client_id && $this->projectSearch && strlen($this->projectSearch) >= 2 && !$this->project_id) {
            $projects = Project::where('client_id', $this->client_id)
                ->where('project_name', 'like', '%' . $this->projectSearch . '%')
                ->orderBy('project_name')
                ->take(10)
                ->get();
        }

        $jobSites = collect();
        if ($this->project_id && $this->jobSiteSearch && strlen($this->jobSiteSearch) >= 1 && !$this->job_site_id) {
            $jobSites = JobSite::where('project_id', $this->project_id)
                ->where('job_site_name', 'like', '%' . $this->jobSiteSearch . '%')
                ->orderBy('job_site_name')
                ->take(10)
                ->get();
        }

        $catalogItems = collect();
        if ($this->catalogItemSearch && strlen($this->catalogItemSearch) >= 2 && !$this->item_catalog_item_id) {
            $catalogItems = CatalogItem::where('is_active', true)
                ->where('name', 'like', '%' . $this->catalogItemSearch . '%')
                ->with('taxRate')
                ->take(10)
                ->get();
        }

        $documentMessages = DocumentMessage::where('type', 'estimate')
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('title')
            ->get();

        $taxRates = TaxRate::orderByDesc('is_default')->orderBy('state')->get();

        return view('livewire.estimate.estimate-edit', [
            'clients' => $clients,
            'projects' => $projects,
            'jobSites' => $jobSites,
            'catalogItems' => $catalogItems,
            'documentMessages' => $documentMessages,
            'taxRates' => $taxRates,
        ])->layout('components.layouts.app');
    }
}
