<?php

namespace App\Livewire\Catalog;

use App\Models\CatalogCategory;
use App\Models\CatalogItem;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class CatalogItemCreate extends Component
{
    public $type = 'product';
    public $name = '';
    public $sku = '';
    public $description = '';
    public $category_id = '';
    public $is_active = true;

    // Product fields
    public $purchase_unit = '';
    public $usage_unit = '';
    public $units_per_purchase = '';

    // Pricing
    public $current_cost = '';
    public $billing_type = '';

    protected function rules()
    {
        $rules = [
            'type' => 'required|in:product,service,rental',
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:255|unique:catalog_items,sku',
            'description' => 'nullable|string',
            'category_id' => 'nullable|exists:catalog_categories,id',
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

        return $rules;
    }

    protected $validationAttributes = [
        'category_id' => 'category',
        'current_cost' => 'cost',
        'purchase_unit' => 'purchase unit',
        'usage_unit' => 'usage unit',
        'units_per_purchase' => 'units per purchase',
        'billing_type' => 'billing type',
    ];

    public function save()
    {
        $this->validate();

        CatalogItem::create([
            'type' => $this->type,
            'name' => $this->name,
            'sku' => $this->sku ?: null,
            'description' => $this->description,
            'category_id' => $this->category_id ?: null,
            'is_active' => $this->is_active,
            'purchase_unit' => $this->type === 'product' ? $this->purchase_unit : null,
            'usage_unit' => $this->type === 'product' ? $this->usage_unit : null,
            'units_per_purchase' => $this->type === 'product' ? $this->units_per_purchase : null,
            'current_cost' => $this->current_cost,
            'billing_type' => in_array($this->type, ['service', 'rental']) ? $this->billing_type : null,
            'created_by' => Auth::id(),
        ]);

        session()->flash('message', 'Catalog item created successfully!');
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

        return view('livewire.catalog.catalog-item-create', [
            'categories' => $categories,
        ])->layout('components.layouts.app');
    }
}
