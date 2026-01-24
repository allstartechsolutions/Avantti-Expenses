<?php

namespace App\Livewire\Supplier;

use App\Models\Supplier;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class SupplierCreate extends Component
{
    public $name = '';
    public $street = '';
    public $address_2 = '';
    public $neighborhood = '';
    public $city = '';
    public $state = '';
    public $postal_code = '';
    public $country;
    public $phone = '';
    public $email = '';
    public $description = '';

    public function mount()
    {
        $this->country = config('app.country', 'US');
    }

    protected $rules = [
        'name' => 'required|string|max:255',
        'street' => 'nullable|string|max:255',
        'address_2' => 'nullable|string|max:255',
        'neighborhood' => 'nullable|string|max:255',
        'city' => 'nullable|string|max:255',
        'state' => 'nullable|string|max:255',
        'postal_code' => 'nullable|string|max:20',
        'country' => 'required|string|size:2',
        'phone' => 'nullable|string|max:20',
        'email' => 'nullable|email|max:255',
        'description' => 'nullable|string|max:1000',
    ];

    protected $validationAttributes = [
        'name' => 'supplier name',
        'address_2' => 'complement',
        'neighborhood' => 'neighborhood',
        'postal_code' => 'postal code',
        'email' => 'email address',
    ];

    public function updated($propertyName)
    {
        $this->validateOnly($propertyName);
    }

    public function createSupplier()
    {
        $this->validate();

        Supplier::create([
            'name' => $this->name,
            'street' => $this->street,
            'address_2' => $this->address_2,
            'neighborhood' => $this->neighborhood,
            'city' => $this->city,
            'state' => $this->state,
            'postal_code' => $this->postal_code,
            'country' => $this->country,
            'phone' => $this->phone,
            'email' => $this->email,
            'description' => $this->description,
            'created_by' => Auth::id(),
        ]);

        session()->flash('message', 'Supplier created successfully!');

        return redirect()->route('suppliers.index');
    }

    public function render()
    {
        return view('livewire.supplier.supplier-create')
            ->layout('components.layouts.app');
    }
}
