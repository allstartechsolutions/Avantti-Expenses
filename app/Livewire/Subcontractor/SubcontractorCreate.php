<?php

namespace App\Livewire\Subcontractor;

use App\Livewire\Concerns\AuthorizesAbility;
use App\Livewire\Concerns\ChecksVendorDuplicates;
use App\Models\Subcontractor;
use App\Models\Vendor;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class SubcontractorCreate extends Component
{
    use AuthorizesAbility;

    use ChecksVendorDuplicates;

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

    protected $rules = [
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

    protected $validationAttributes = [
        'company_name' => 'company name',
        'contact_name' => 'contact name',
        'contact_email' => 'contact email',
        'title' => 'title',
        'postal_code' => 'postal code',
        'address_2' => 'address line 2',
    ];

    public function mount()
    {
        $this->authorizeAbility('vendors.create');

        $this->country = config('app.country', 'US');
    }

    public function updated($propertyName)
    {
        $this->validateOnly($propertyName);

        if ($propertyName === 'company_name') {
            $this->refreshDuplicateMatches($this->company_name);
        }
    }

    /**
     * Flag an existing vendor (found by the duplicate check) as a
     * subcontractor instead of creating a second record for the same company.
     */
    public function markAsSubcontractor(int $vendorId)
    {
        $this->authorizeAbility('vendors.edit');

        $vendor = Vendor::findOrFail($vendorId);
        $vendor->is_subcontractor = true;
        $vendor->save();

        session()->flash('message', __(':name is now also registered as a subcontractor.', ['name' => $vendor->name]));

        return redirect()->route('subcontractors.index');
    }

    public function createSubcontractor()
    {
        $this->authorizeAbility('vendors.create');

        $this->validate();

        $subcontractor = new Subcontractor([
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
            'created_by' => Auth::id(),
        ]);
        $subcontractor->is_supplier = (bool) $this->also_supplier;
        $subcontractor->save();

        session()->flash('message', __('Subcontractor created successfully!'));

        return redirect()->route('subcontractors.index');
    }

    public function render()
    {
        return view('livewire.subcontractor.subcontractor-create')
            ->layout('components.layouts.app');
    }
}
