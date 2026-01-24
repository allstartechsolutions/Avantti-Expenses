<?php

namespace App\Livewire\Project;

use App\Enums\ProjectStatus;
use App\Models\Client;
use App\Models\Project;
use Livewire\Component;

class ProjectEdit extends Component
{
    public Project $project;
    public $client_id = '';
    public $clientSearch = '';
    public $showClientDropdown = false;
    public $project_name = '';
    public $street = '';
    public $address_2 = '';
    public $city = '';
    public $state = '';
    public $postal_code = '';
    public $neighborhood = '';
    public $latitude = null;
    public $longitude = null;
    public $contact_person = '';
    public $phone = '';
    public $email = '';
    public $initial_amount = '';
    public $description = '';
    public $status = '';

    protected function rules()
    {
        return [
            'client_id' => 'required|exists:clients,id',
            'project_name' => 'required|string|max:255',
            'street' => 'nullable|string|max:255',
            'address_2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'neighborhood' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'contact_person' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'required|email|max:255',
            'initial_amount' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'status' => 'required|in:created,in_progress,completed,cancelled',
        ];
    }

    protected $validationAttributes = [
        'client_id' => 'client',
        'project_name' => 'project name',
        'contact_person' => 'contact person',
        'postal_code' => 'postal code',
        'email' => 'email address',
        'initial_amount' => 'initial amount',
    ];

    public function mount(Project $project)
    {
        $this->project = $project;
        $this->client_id = $project->client_id;
        $this->clientSearch = $project->client->company_name;
        $this->project_name = $project->project_name;
        $this->street = $project->street;
        $this->address_2 = $project->address_2;
        $this->city = $project->city;
        $this->state = $project->state;
        $this->postal_code = $project->postal_code;
        $this->neighborhood = $project->neighborhood;
        $this->latitude = $project->latitude;
        $this->longitude = $project->longitude;
        $this->contact_person = $project->contact_person;
        $this->phone = $project->phone;
        $this->email = $project->email;
        $this->initial_amount = $project->initial_amount;
        $this->description = $project->description;
        $this->status = $project->status->value;
    }

    public function updated($propertyName)
    {
        $this->validateOnly($propertyName);
    }

    public function updatedClientSearch()
    {
        $this->showClientDropdown = !empty($this->clientSearch);
    }

    public function selectClient($clientId)
    {
        $client = Client::find($clientId);

        if ($client) {
            $this->client_id = $client->id;
            $this->clientSearch = $client->company_name;
            $this->showClientDropdown = false;

            // Auto-populate fields from client
            $this->street = $client->street ?? '';
            $this->city = $client->city ?? '';
            $this->state = $client->state ?? '';
            $this->postal_code = $client->postal_code ?? '';
            $this->contact_person = $client->contact_name;
            $this->phone = $client->phone ?? '';
            $this->email = $client->email;
        }
    }

    public function updateProject()
    {
        $this->validate();

        $this->project->update([
            'client_id' => $this->client_id,
            'project_name' => $this->project_name,
            'street' => $this->street,
            'address_2' => $this->address_2,
            'city' => $this->city,
            'state' => $this->state,
            'postal_code' => $this->postal_code,
            'neighborhood' => $this->neighborhood,
            'country' => config('app.country'),
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'contact_person' => $this->contact_person,
            'phone' => $this->phone,
            'email' => $this->email,
            'initial_amount' => $this->initial_amount,
            'description' => $this->description,
            'status' => $this->status,
        ]);

        session()->flash('message', 'Project updated successfully!');

        return redirect()->route('projects.index');
    }

    public function render()
    {
        $clients = [];

        if (!empty($this->clientSearch) && $this->showClientDropdown) {
            $clients = Client::query()
                ->where('company_name', 'like', '%' . $this->clientSearch . '%')
                ->orWhere('contact_name', 'like', '%' . $this->clientSearch . '%')
                ->orWhere('email', 'like', '%' . $this->clientSearch . '%')
                ->limit(10)
                ->get();
        }

        $statuses = ProjectStatus::cases();

        return view('livewire.project.project-edit', [
            'clients' => $clients,
            'statuses' => $statuses,
        ])->layout('components.layouts.app');
    }
}
