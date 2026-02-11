<?php

namespace App\Livewire\Client;

use App\Models\Client;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class ClientQuickCreate extends Component
{
    public $showModal = false;

    public $company_name = '';
    public $contact_name = '';
    public $title = '';
    public $street = '';
    public $city = '';
    public $state = '';
    public $postal_code = '';
    public $phone = '';
    public $email = '';
    public $website = '';

    protected $rules = [
        'company_name' => 'required|string|max:255',
        'contact_name' => 'required|string|max:255',
        'title' => 'nullable|string|max:255',
        'street' => 'nullable|string|max:255',
        'city' => 'nullable|string|max:255',
        'state' => 'nullable|string|max:255',
        'postal_code' => 'nullable|string|max:20',
        'phone' => 'nullable|string|max:20',
        'email' => 'required|email|max:255',
        'website' => 'nullable|url|max:255',
    ];

    protected $validationAttributes = [
        'company_name' => 'company name',
        'contact_name' => 'contact name',
        'postal_code' => 'postal code',
        'email' => 'email address',
        'website' => 'website',
    ];

    #[On('open-quick-add-client')]
    public function openModal($companyName = '')
    {
        $this->resetForm();
        $this->company_name = $companyName;
        $this->showModal = true;
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->resetForm();
    }

    protected function resetForm()
    {
        $this->reset([
            'company_name', 'contact_name', 'title', 'street',
            'city', 'state', 'postal_code', 'phone', 'email', 'website',
        ]);
        $this->resetValidation();
    }

    public function updated($propertyName)
    {
        $this->validateOnly($propertyName);
    }

    public function saveClient()
    {
        $this->validate();

        $client = Client::create([
            'company_name' => $this->company_name,
            'contact_name' => $this->contact_name,
            'title' => $this->title,
            'street' => $this->street,
            'city' => $this->city,
            'state' => $this->state,
            'postal_code' => $this->postal_code,
            'phone' => $this->phone,
            'email' => $this->email,
            'website' => $this->website,
            'created_by' => Auth::id(),
        ]);

        $this->showModal = false;
        $this->resetForm();

        $this->dispatch('client-quick-created', clientId: $client->id);
    }

    public function render()
    {
        return view('livewire.client.client-quick-create');
    }
}
