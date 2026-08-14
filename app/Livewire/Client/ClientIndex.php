<?php

namespace App\Livewire\Client;

use App\Models\Client;
use App\Models\Project;
use Livewire\Component;
use Livewire\WithPagination;

class ClientIndex extends Component
{
    use WithPagination;

    public $search = '';
    public $perPage = 10;

    // Delete modal
    public $showDeleteModal = false;
    public $deletingClientId = null;
    public $deleteClientData = [];

    protected $queryString = [
        'search' => ['except' => ''],
    ];

    public function updatingSearch()
    {
        $this->resetPage();
    }

    public function confirmDeleteClient($clientId)
    {
        $client = Client::findOrFail($clientId);
        $projectsCount = Project::where('client_id', $clientId)->count();

        if ($projectsCount > 0) {
            return;
        }

        $this->deletingClientId = $clientId;
        $this->deleteClientData = [
            'name' => $client->company_name,
        ];

        $this->showDeleteModal = true;
        $this->dispatch('open-modal', 'delete-client-modal');
    }

    public function deleteClient()
    {
        $client = Client::findOrFail($this->deletingClientId);

        // Re-check as a safety guard
        if (Project::where('client_id', $client->id)->exists()) {
            $this->cancelDeleteClient();
            return;
        }

        $client->delete();

        $this->showDeleteModal = false;
        $this->deletingClientId = null;
        $this->deleteClientData = [];

        session()->flash('message', __('Client deleted successfully!'));
    }

    public function cancelDeleteClient()
    {
        $this->showDeleteModal = false;
        $this->deletingClientId = null;
        $this->deleteClientData = [];
        $this->dispatch('close-modal', 'delete-client-modal');
    }

    public function render()
    {
        $clients = Client::query()
            ->with('createdBy')
            ->withCount('projects')
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('company_name', 'like', '%' . $this->search . '%')
                      ->orWhere('contact_name', 'like', '%' . $this->search . '%')
                      ->orWhere('email', 'like', '%' . $this->search . '%')
                      ->orWhere('phone', 'like', '%' . $this->search . '%');
                });
            })
            ->orderBy('created_at', 'desc')
            ->paginate($this->perPage);

        return view('livewire.client.client-index', [
            'clients' => $clients,
        ])->layout('components.layouts.app');
    }
}
