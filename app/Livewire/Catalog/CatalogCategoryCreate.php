<?php

namespace App\Livewire\Catalog;

use App\Models\CatalogCategory;
use Livewire\Component;

class CatalogCategoryCreate extends Component
{
    public $name = '';
    public $applicable_types = [];
    public $parent_id = '';
    public $is_active = true;
    public $display_order = 0;

    protected $rules = [
        'name' => 'required|string|max:255|unique:catalog_categories,name',
        'applicable_types' => 'required|array|min:1',
        'applicable_types.*' => 'in:product,service,rental',
        'parent_id' => 'nullable|exists:catalog_categories,id',
        'is_active' => 'boolean',
        'display_order' => 'integer|min:0',
    ];

    protected $validationAttributes = [
        'applicable_types' => 'applicable types',
        'parent_id' => 'parent category',
        'display_order' => 'display order',
    ];

    public function save()
    {
        $this->validate();

        CatalogCategory::create([
            'name' => $this->name,
            'applicable_types' => $this->applicable_types,
            'parent_id' => $this->parent_id ?: null,
            'is_active' => $this->is_active,
            'display_order' => $this->display_order,
        ]);

        session()->flash('message', 'Category created successfully!');
        return redirect()->route('catalog.categories.index');
    }

    public function render()
    {
        $parentCategories = CatalogCategory::whereNull('parent_id')
            ->orderBy('name')
            ->get();

        return view('livewire.catalog.catalog-category-create', [
            'parentCategories' => $parentCategories,
        ])->layout('components.layouts.app');
    }
}
