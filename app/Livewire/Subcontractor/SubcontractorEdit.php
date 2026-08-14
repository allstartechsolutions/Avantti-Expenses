<?php

namespace App\Livewire\Subcontractor;

use App\Models\Subcontractor;
use Livewire\Component;

class SubcontractorEdit extends Component
{
    public Subcontractor $subcontractor;

    // Company Information
    public $company_name = '';
    public $website = '';
    public $also_supplier = false;

    // Contact Person
    public $contact_name = '';
    public $contact_email = '';
    public $title = '';
    public $phone = '';

    // Address
    public $street = '';
    public $address_2 = '';
    public $neighborhood = '';
    public $city = '';
    public $state = '';
    public $postal_code = '';
    public $country = '';
    public $latitude = null;
    public $longitude = null;

    protected function rules()
    {
        return [
            'company_name' => 'required|string|max:255',
            'website' => 'nullable|url|max:255',
            'contact_name' => 'required|string|max:255',
            'contact_email' => 'required|email|max:255',
            'title' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'street' => 'nullable|string|max:255',
            'address_2' => 'nullable|string|max:255',
            'neighborhood' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ];
    }

    protected $validationAttributes = [
        'company_name' => 'company name',
        'contact_name' => 'contact name',
        'contact_email' => 'contact email',
        'title' => 'title',
        'postal_code' => 'postal code',
        'address_2' => 'address line 2',
    ];

    public function mount(Subcontractor $subcontractor)
    {
        $this->subcontractor = $subcontractor;
        $this->company_name = $subcontractor->company_name;
        $this->website = $subcontractor->website;
        $this->contact_name = $subcontractor->contact_name;
        $this->contact_email = $subcontractor->contact_email;
        $this->title = $subcontractor->title;
        $this->phone = $subcontractor->phone;
        $this->street = $subcontractor->street;
        $this->address_2 = $subcontractor->address_2;
        $this->neighborhood = $subcontractor->neighborhood;
        $this->city = $subcontractor->city;
        $this->state = $subcontractor->state;
        $this->postal_code = $subcontractor->postal_code;
        $this->country = $subcontractor->country ?? config('app.country', 'US');
        $this->latitude = $subcontractor->latitude;
        $this->longitude = $subcontractor->longitude;
        $this->also_supplier = (bool) $subcontractor->is_supplier;
    }

    public function updated($propertyName)
    {
        $this->validateOnly($propertyName);
    }

    public function updateSubcontractor()
    {
        $this->validate();

        // Removing the supplier classification is blocked while expenses,
        // catalog items or purchase orders reference this company.
        if (! $this->also_supplier && $this->subcontractor->is_supplier
            && \App\Models\Vendor::hasSupplierRecords($this->subcontractor->id)) {
            $this->addError('also_supplier', __('This company has expenses, catalog items or purchase orders and cannot stop being a supplier.'));

            return;
        }

        $this->subcontractor->is_supplier = $this->also_supplier;

        $this->subcontractor->update([
            'company_name' => $this->company_name,
            'website' => $this->website,
            'contact_name' => $this->contact_name,
            'contact_email' => $this->contact_email,
            'title' => $this->title,
            'phone' => $this->phone,
            'street' => $this->street,
            'address_2' => $this->address_2,
            'neighborhood' => $this->neighborhood,
            'city' => $this->city,
            'state' => $this->state,
            'postal_code' => $this->postal_code,
            'country' => $this->country,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
        ]);

        session()->flash('message', __('Subcontractor updated successfully!'));

        return redirect()->route('subcontractors.index');
    }

    public function render()
    {
        return view('livewire.subcontractor.subcontractor-edit')
            ->layout('components.layouts.app');
    }
}
