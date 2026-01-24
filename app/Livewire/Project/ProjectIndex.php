<?php

namespace App\Livewire\Project;

use App\Enums\ProjectStatus;
use App\Models\Client;
use App\Models\Project;
use Livewire\Component;
use Livewire\WithPagination;

class ProjectIndex extends Component
{
    use WithPagination;

    public $search = '';
    public $statusFilter = '';
    public $clientFilter = '';
    public $perPage = 10;

    protected $queryString = [
        'search' => ['except' => ''],
        'statusFilter' => ['except' => ''],
        'clientFilter' => ['except' => ''],
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function updatingStatusFilter()
    {
        $this->resetPage();
    }

    public function updatingClientFilter()
    {
        $this->resetPage();
    }

    public function clearFilters()
    {
        $this->reset(['search', 'statusFilter', 'clientFilter']);
        $this->resetPage();
    }

    public function render()
    {
        $projects = Project::query()
            ->with(['client', 'createdBy', 'jobSites'])
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('project_name', 'like', '%' . $this->search . '%')
                      ->orWhere('contact_person', 'like', '%' . $this->search . '%')
                      ->orWhere('email', 'like', '%' . $this->search . '%')
                      ->orWhereHas('client', function($q) {
                          $q->where('company_name', 'like', '%' . $this->search . '%');
                      });
                });
            })
            ->when($this->statusFilter, function ($query) {
                $query->where('status', $this->statusFilter);
            })
            ->when($this->clientFilter, function ($query) {
                $query->where('client_id', $this->clientFilter);
            })
            ->orderBy('created_at', 'desc')
            ->paginate($this->perPage);

        $clients = Client::orderBy('company_name')->get();
        $statuses = ProjectStatus::cases();

        return view('livewire.project.project-index', [
            'projects' => $projects,
            'clients' => $clients,
            'statuses' => $statuses,
        ])->layout('components.layouts.app');
    }
}
