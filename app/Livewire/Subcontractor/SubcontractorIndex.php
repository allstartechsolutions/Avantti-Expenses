<?php

namespace App\Livewire\Subcontractor;

use App\Models\Subcontractor;
use Livewire\Component;
use Livewire\WithPagination;

class SubcontractorIndex extends Component
{
    use WithPagination;

    public $search = '';
    public $perPage = 10;

    protected $queryString = [
        'search' => ['except' => ''],
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function render()
    {
        $subcontractors = Subcontractor::query()
            ->with('createdBy')
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('company_name', 'like', '%' . $this->search . '%')
                      ->orWhere('contact_name', 'like', '%' . $this->search . '%')
                      ->orWhere('contact_email', 'like', '%' . $this->search . '%')
                      ->orWhere('phone', 'like', '%' . $this->search . '%');
                });
            })
            ->orderBy('created_at', 'desc')
            ->paginate($this->perPage);

        return view('livewire.subcontractor.subcontractor-index', [
            'subcontractors' => $subcontractors,
        ])->layout('components.layouts.app');
    }
}
