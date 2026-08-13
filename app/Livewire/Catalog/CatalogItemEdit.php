<?php

namespace App\Livewire\Catalog;

use App\Models\CatalogCategory;
use App\Models\CatalogItem;
use App\Models\Supplier;
use App\Models\TaxRate;
use Livewire\Component;

class CatalogItemEdit extends Component
{
    public CatalogItem $item;

    public $type;
    public $name;
    public $sku;
    public $description;
    public $category_id;
    public $supplier_id;
    public $is_active;

    // Product fields
    public $purchase_unit;
    public $usage_unit;
    public $units_per_purchase;

    // Pricing
    public $current_cost;
    public $billing_type;

    // Tax
    public $is_taxable;
    public $tax_rate_id;

    public function mount(CatalogItem $item)
    {
        $this->item = $item;
        $this->type = $item->type;
        $this->name = $item->name;
        $this->sku = $item->sku;
        $this->description = $item->description;
        $this->category_id = $item->category_id;
        $this->supplier_id = $item->supplier_id;
        $this->is_active = $item->is_active;
        $this->purchase_unit = $item->purchase_unit;
        $this->usage_unit = $item->usage_unit;
        $this->units_per_purchase = $item->units_per_purchase;
        $this->current_cost = $item->current_cost;
        $this->billing_type = $item->billing_type;
        $this->is_taxable = $item->is_taxable;
        $this->tax_rate_id = $item->tax_rate_id;
    }

    protected function rules()
    {
        $rules = [
            'type' => 'required|in:product,service,rental',
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:255|unique:catalog_items,sku,' . $this->item->id,
            'description' => 'nullable|string',
            'category_id' => 'nullable|exists:catalog_categories,id',
            'supplier_id' => 'nullable|exists:vendors,id,is_supplier,1',
            'is_active' => 'boolean',
            'current_cost' => 'required|numeric|min:0',
        ];

        if ($this->type === 'product') {
            $rules['purchase_unit'] = 'required|string|max:50';
            $rules['usage_unit'] = 'required|string|max:50';
            $rules['units_per_purchase'] = 'required|numeric|min:0.01';
        }

        if (in_array($this->type, ['service', 'rental'])) {
            $rules['billing_type'] = 'required|in:hourly,fixed,daily,weekly,monthly';
        }

        $rules['is_taxable'] = 'boolean';
        if ($this->is_taxable) {
            $rules['tax_rate_id'] = 'required|exists:tax_rates,id';
        }

        return $rules;
    }

    public function updatedIsTaxable($value)
    {
        if ($value) {
            $default = TaxRate::where('is_default', true)->first();
            $this->tax_rate_id = $default?->id ?? '';
        } else {
            $this->tax_rate_id = '';
        }
    }

    protected $validationAttributes = [
        'category_id' => 'category',
        'supplier_id' => 'preferred supplier',
        'current_cost' => 'cost',
        'purchase_unit' => 'purchase unit',
        'usage_unit' => 'usage unit',
        'units_per_purchase' => 'units per purchase',
        'billing_type' => 'billing type',
        'tax_rate_id' => 'tax rate',
    ];

    public function save()
    {
        $this->validate();

        // Price history is automatically tracked in the model boot method
        $this->item->update([
            'type' => $this->type,
            'name' => $this->name,
            'sku' => $this->sku ?: null,
            'description' => $this->description,
            'category_id' => $this->category_id ?: null,
            'supplier_id' => in_array($this->type, ['product', 'rental']) && $this->supplier_id ? $this->supplier_id : null,
            'is_active' => $this->is_active,
            'purchase_unit' => $this->type === 'product' ? $this->purchase_unit : null,
            'usage_unit' => $this->type === 'product' ? $this->usage_unit : null,
            'units_per_purchase' => $this->type === 'product' ? $this->units_per_purchase : null,
            'current_cost' => $this->current_cost,
            'billing_type' => in_array($this->type, ['service', 'rental']) ? $this->billing_type : null,
            'is_taxable' => $this->is_taxable,
            'tax_rate_id' => $this->is_taxable && $this->tax_rate_id ? $this->tax_rate_id : null,
        ]);

        session()->flash('message', 'Catalog item updated successfully!');
        return redirect()->route('catalog.index');
    }

    public function render()
    {
        $categories = CatalogCategory::active()
            ->where(function ($query) {
                $query->whereJsonContains('applicable_types', $this->type)
                    ->orWhereNull('applicable_types');
            })
            ->orderBy('name')
            ->get();

        $suppliers = Supplier::orderBy('name')->get();
        $taxRates = TaxRate::orderBy('state')->get();

        $priceHistory = $this->item->priceHistory()->with('changedBy')->take(10)->get();

        return view('livewire.catalog.catalog-item-edit', [
            'categories' => $categories,
            'suppliers' => $suppliers,
            'taxRates' => $taxRates,
            'priceHistory' => $priceHistory,
        ])->layout('components.layouts.app');
    }
}
