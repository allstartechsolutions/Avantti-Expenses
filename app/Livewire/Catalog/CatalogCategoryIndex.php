<?php

namespace App\Livewire\Catalog;

use App\Models\CatalogCategory;
use Livewire\Component;
use Livewire\WithPagination;

class CatalogCategoryIndex extends Component
{
    use WithPagination;

    public $search = '';
    public $filterType = '';
    public $filterStatus = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'filterType' => ['except' => ''],
        'filterStatus' => ['except' => ''],
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingFilterType()
    {
        $this->resetPage();
    }

    public function updatingFilterStatus()
    {
        $this->resetPage();
    }

    public function deleteCategory($id)
    {
        $category = CatalogCategory::findOrFail($id);

        // Check if category has items
        if ($category->items()->count() > 0) {
            session()->flash('error', 'Cannot delete category with associated catalog items.');
            return;
        }

        // Check if category has children
        if ($category->children()->count() > 0) {
            session()->flash('error', 'Cannot delete category with subcategories.');
            return;
        }

        $category->delete();
        session()->flash('message', 'Category deleted successfully.');
    }

    public function render()
    {
        $categories = CatalogCategory::query()
            ->with(['parent', 'items'])
            ->withCount('items')
            ->when($this->search, function ($query) {
                $query->where('name', 'like', '%' . $this->search . '%');
            })
            ->when($this->filterType, function ($query) {
                $query->whereJsonContains('applicable_types', $this->filterType);
            })
            ->when($this->filterStatus !== '', function ($query) {
                $query->where('is_active', $this->filterStatus === 'active');
            })
            ->orderBy('display_order')
            ->orderBy('name')
            ->paginate(15);

        return view('livewire.catalog.catalog-category-index', [
            'categories' => $categories,
        ])->layout('components.layouts.app');
    }
}
