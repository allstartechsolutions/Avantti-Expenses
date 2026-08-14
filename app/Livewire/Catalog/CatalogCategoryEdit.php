<?php

namespace App\Livewire\Catalog;

use App\Models\CatalogCategory;
use Livewire\Component;

class CatalogCategoryEdit extends Component
{
    public CatalogCategory $category;

    public $name = '';
    public $applicable_types = [];
    public $parent_id = '';
    public $is_active = true;
    public $display_order = 0;

    public function mount(CatalogCategory $category)
    {
        $this->category = $category;
        $this->name = $category->name;
        $this->applicable_types = $category->applicable_types ?? [];
        $this->parent_id = $category->parent_id;
        $this->is_active = $category->is_active;
        $this->display_order = $category->display_order;
    }

    protected function rules()
    {
        return [
            'name' => 'required|string|max:255|unique:catalog_categories,name,' . $this->category->id,
            'applicable_types' => 'required|array|min:1',
            'applicable_types.*' => 'in:product,service,rental',
            'parent_id' => 'nullable|exists:catalog_categories,id',
            'is_active' => 'boolean',
            'display_order' => 'integer|min:0',
        ];
    }

    protected $validationAttributes = [
        'applicable_types' => 'applicable types',
        'parent_id' => 'parent category',
        'display_order' => 'display order',
    ];

    public function save()
    {
        $this->validate();

        // Prevent setting self as parent
        if ($this->parent_id == $this->category->id) {
            $this->addError('parent_id', 'A category cannot be its own parent.');
            return;
        }

        $this->category->update([
            'name' => $this->name,
            'applicable_types' => $this->applicable_types,
            'parent_id' => $this->parent_id ?: null,
            'is_active' => $this->is_active,
            'display_order' => $this->display_order,
        ]);

        session()->flash('message', __('Category updated successfully!'));
        return redirect()->route('catalog.categories.index');
    }

    public function render()
    {
        $parentCategories = CatalogCategory::whereNull('parent_id')
            ->where('id', '!=', $this->category->id)
            ->orderBy('name')
            ->get();

        return view('livewire.catalog.catalog-category-edit', [
            'parentCategories' => $parentCategories,
        ])->layout('components.layouts.app');
    }
}
