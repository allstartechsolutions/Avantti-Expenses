<?php

namespace App\Livewire\Catalog;

use App\Livewire\Concerns\AuthorizesAbility;
use App\Models\CatalogCategory;
use App\Models\CatalogItem;
use Livewire\Component;
use Livewire\WithPagination;

class CatalogItemIndex extends Component
{
    use AuthorizesAbility;

    use WithPagination;

    public $search = '';
    public $typeFilter = '';
    public $categoryFilter = '';
    public $statusFilter = '';

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingTypeFilter()
    {
        $this->resetPage();
    }

    public function updatingCategoryFilter()
    {
        $this->resetPage();
    }

    public function updatingStatusFilter()
    {
        $this->resetPage();
    }

    public function render()
    {
        $query = CatalogItem::with(['category', 'createdBy']);

        // Apply search
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', '%' . $this->search . '%')
                    ->orWhere('sku', 'like', '%' . $this->search . '%')
                    ->orWhere('description', 'like', '%' . $this->search . '%');
            });
        }

        // Apply type filter
        if ($this->typeFilter) {
            $query->where('type', $this->typeFilter);
        }

        // Apply category filter
        if ($this->categoryFilter) {
            $query->where('category_id', $this->categoryFilter);
        }

        // Apply status filter
        if ($this->statusFilter === 'active') {
            $query->where('is_active', true);
        } elseif ($this->statusFilter === 'inactive') {
            $query->where('is_active', false);
        }

        $items = $query->orderBy('name')->paginate(15);
        $categories = CatalogCategory::active()->orderBy('name')->get();

        return view('livewire.catalog.catalog-item-index', [
            'items' => $items,
            'categories' => $categories,
        ])->layout('components.layouts.app');
    }
}
