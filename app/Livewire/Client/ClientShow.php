<?php

namespace App\Livewire\Client;

use App\Models\Client;
use App\Models\Project;
use Livewire\Component;

class ClientShow extends Component
{
    public Client $client;
    public int $projectsCount = 0;

    // Delete modal
    public $showDeleteModal = false;
    public $deleteClientData = [];

    public function mount(Client $client)
    {
        $this->client = $client;
        $this->projectsCount = Project::where('client_id', $client->id)->count();
    }

    public function confirmDeleteClient()
    {
        if ($this->projectsCount > 0) {
            return;
        }

        $this->deleteClientData = [
            'name' => $this->client->company_name,
        ];

        $this->showDeleteModal = true;
        $this->dispatch('open-modal', 'delete-client-modal');
    }

    public function deleteClient()
    {
        // Re-check as a safety guard
        if (Project::where('client_id', $this->client->id)->exists()) {
            $this->cancelDeleteClient();
            return;
        }

        $this->client->delete();

        session()->flash('message', 'Client deleted successfully!');
        return $this->redirect(route('clients.index'), navigate: true);
    }

    public function cancelDeleteClient()
    {
        $this->showDeleteModal = false;
        $this->deleteClientData = [];
        $this->dispatch('close-modal', 'delete-client-modal');
    }

    public function render()
    {
        return view('livewire.client.client-show')
            ->layout('components.layouts.app');
    }
}
