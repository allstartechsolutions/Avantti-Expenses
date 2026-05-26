<?php

namespace App\Livewire\Project;

use App\Enums\ProjectAmountSource;
use App\Enums\ProjectStatus;
use App\Enums\UserStatus;
use App\Models\Client;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class ProjectCreate extends Component
{
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
    public $amount_source = 'manual';
    public $description = '';
    public $status = 'created';
    public $project_manager_id = '';

    protected function rules(): array
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
            'amount_source' => 'required|in:manual,from_jobsites',
            'initial_amount' => $this->amount_source === 'manual' ? 'required|numeric|min:0' : 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'status' => 'required|in:created,in_progress,completed,cancelled',
            'project_manager_id' => 'nullable|exists:users,id',
        ];
    }

    public function validationAttributes()
    {
        return [
            'client_id' => __('client'),
            'project_name' => __('project name'),
            'contact_person' => __('contact person'),
            'postal_code' => __('postal code'),
            'email' => __('email address'),
            'initial_amount' => __('initial amount'),
            'project_manager_id' => __('project manager'),
        ];
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

    public function createProject()
    {
        $this->validate();

        $project = Project::create([
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
            'initial_amount' => $this->amount_source === 'manual' ? $this->initial_amount : 0,
            'amount_source' => $this->amount_source,
            'description' => $this->description,
            'status' => $this->status,
            'project_manager_id' => $this->project_manager_id ?: null,
            'created_by' => Auth::id(),
        ]);

        session()->flash('message', __('Project created successfully!'));

        return redirect()->route('projects.overview', $project);
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

        $users = User::where('status', UserStatus::ACTIVE)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('livewire.project.project-create', [
            'clients' => $clients,
            'statuses' => $statuses,
            'users' => $users,
        ])->layout('components.layouts.app');
    }
}
