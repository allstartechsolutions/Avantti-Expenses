<?php

namespace App\Livewire\Supplier;

use App\Models\Supplier;
use Livewire\Component;

class SupplierEdit extends Component
{
    public Supplier $supplier;
    public $name = '';
    public $also_subcontractor = false;
    public $street = '';
    public $address_2 = '';
    public $neighborhood = '';
    public $city = '';
    public $state = '';
    public $postal_code = '';
    public $country = '';
    public $phone = '';
    public $email = '';
    public $description = '';

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

    public function mount(Supplier $supplier)
    {
        $this->supplier = $supplier;
        $this->name = $supplier->name;
        $this->street = $supplier->street;
        $this->address_2 = $supplier->address_2;
        $this->neighborhood = $supplier->neighborhood;
        $this->city = $supplier->city;
        $this->state = $supplier->state;
        $this->postal_code = $supplier->postal_code;
        $this->country = $supplier->country ?? config('app.country', 'US');
        $this->phone = $supplier->phone;
        $this->email = $supplier->email;
        $this->description = $supplier->description;
        $this->also_subcontractor = (bool) $supplier->is_subcontractor;
    }

    public function updated($propertyName)
    {
        $this->validateOnly($propertyName);
    }

    public function updateSupplier()
    {
        $this->validate();

        // Removing the subcontractor classification is blocked while
        // contracts or payment batches reference this company.
        if (! $this->also_subcontractor && $this->supplier->is_subcontractor
            && \App\Models\Vendor::hasSubcontractorRecords($this->supplier->id)) {
            $this->addError('also_subcontractor', __('This company has contracts or payment batches and cannot stop being a subcontractor.'));

            return;
        }

        $this->supplier->is_subcontractor = $this->also_subcontractor;

        $this->supplier->update([
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
        ]);

        session()->flash('message', __('Supplier updated successfully!'));

        return redirect()->route('suppliers.index');
    }

    public function render()
    {
        return view('livewire.supplier.supplier-edit')
            ->layout('components.layouts.app');
    }
}
