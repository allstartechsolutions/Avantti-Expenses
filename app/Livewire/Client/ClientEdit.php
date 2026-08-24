<?php

namespace App\Livewire\Client;

use App\Livewire\Concerns\AuthorizesAbility;
use App\Models\Client;
use Illuminate\Validation\Rule;
use Livewire\Component;

class ClientEdit extends Component
{
    use AuthorizesAbility;

    public Client $client;
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

    protected function rules()
    {
        return [
            'company_name' => 'required|string|max:255',
            'contact_name' => 'required|string|max:255',
            'title' => 'nullable|string|max:255',
            'street' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'phone' => 'nullable|string|max:20',
            'email' => ['required', 'email', 'max:255', Rule::unique('clients', 'email')->ignore($this->client->id)],
            'website' => 'nullable|url|max:255',
        ];
    }


    public function mount(Client $client)
    {
        $this->authorizeAbility('clients.edit');

        $this->client = $client;
        $this->company_name = $client->company_name;
        $this->contact_name = $client->contact_name;
        $this->title = $client->title;
        $this->street = $client->street;
        $this->city = $client->city;
        $this->state = $client->state;
        $this->postal_code = $client->postal_code;
        $this->phone = $client->phone;
        $this->email = $client->email;
        $this->website = $client->website;
    }

    public function updated($propertyName)
    {
        $this->validateOnly($propertyName);
    }

    public function updateClient()
    {
        $this->validate();

        $this->client->update([
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
        ]);

        session()->flash('message', __('Client updated successfully!'));

        return redirect()->route('clients.index');
    }

    public function render()
    {
        return view('livewire.client.client-edit')
            ->layout('components.layouts.app');
    }
}
