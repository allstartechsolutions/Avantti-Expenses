<?php

namespace App\Livewire\Client;

use App\Models\Client;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class ClientCreate extends Component
{
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
        'title' => 'title',
        'postal_code' => 'postal code',
        'email' => 'email address',
        'website' => 'website',
    ];

    public function updated($propertyName)
    {
        $this->validateOnly($propertyName);
    }

    public function createClient()
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

        session()->flash('message', 'Client created successfully!');

        return redirect()->route('clients.index');
    }

    public function render()
    {
        return view('livewire.client.client-create')
            ->layout('components.layouts.app');
    }
}
