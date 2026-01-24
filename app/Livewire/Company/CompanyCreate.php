<?php

namespace App\Livewire\Company;

use App\Models\Company;
use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class CompanyCreate extends Component
{
    use WithFileUploads;

    public ?Company $company = null;
    public $name = '';
    public $street = '';
    public $city = '';
    public $state = '';
    public $postal_code = '';
    public $email = '';
    public $website = '';
    public $phone = '';
    public $mobile = '';
    public $fax = '';
    public $logo;
    public $logoPreview;
    public $existingLogo = null;

    protected $rules = [
        'name' => 'required|string|max:255',
        'street' => 'required|string|max:255',
        'city' => 'required|string|max:255',
        'state' => 'required|string|max:255',
        'postal_code' => 'required|string|max:20',
        'email' => 'required|email|max:255',
        'website' => 'nullable|url|max:255',
        'phone' => 'required|string|max:20',
        'mobile' => 'nullable|string|max:20',
        'fax' => 'nullable|string|max:20',
        'logo' => 'nullable|image|max:2048',
    ];

    protected $validationAttributes = [
        'name' => 'company name',
        'postal_code' => 'postal code',
    ];

    public function mount()
    {
        // Load existing company if it exists (should only be one)
        $this->company = Company::first();

        if ($this->company) {
            $this->name = $this->company->name;
            $this->street = $this->company->street;
            $this->city = $this->company->city;
            $this->state = $this->company->state;
            $this->postal_code = $this->company->postal_code;
            $this->email = $this->company->email;
            $this->website = $this->company->website;
            $this->phone = $this->company->phone;
            $this->mobile = $this->company->mobile;
            $this->fax = $this->company->fax;
            $this->existingLogo = $this->company->logo;
        }
    }

    public function updated($propertyName)
    {
        $this->validateOnly($propertyName);
    }

    public function updatedLogo()
    {
        $this->validateOnly('logo');

        if ($this->logo) {
            $this->logoPreview = $this->logo->temporaryUrl();
        }
    }

    public function removeLogo()
    {
        $this->logo = null;
        $this->logoPreview = null;
    }

    public function removeExistingLogo()
    {
        if ($this->company && $this->company->logo) {
            Storage::disk('public')->delete($this->company->logo);
            $this->company->logo = null;
            $this->company->save();
            $this->existingLogo = null;
            session()->flash('message', 'Logo removed successfully!');
        }
    }

    public function saveCompany()
    {
        $this->validate();

        if ($this->company) {
            // Update existing company
            $company = $this->company;
        } else {
            // Create new company
            $company = new Company();
            $company->created_by = Auth::id();
        }

        $company->name = $this->name;
        $company->street = $this->street;
        $company->city = $this->city;
        $company->state = $this->state;
        $company->postal_code = $this->postal_code;
        $company->email = $this->email;
        $company->website = $this->website;
        $company->phone = $this->phone;
        $company->mobile = $this->mobile;
        $company->fax = $this->fax;

        if ($this->logo) {
            // Delete old logo if updating
            if ($company->logo) {
                Storage::disk('public')->delete($company->logo);
            }
            $company->logo = $this->logo->store('company-logos', 'public');
        }

        $company->save();

        $message = $this->company ? 'Company updated successfully!' : 'Company created successfully!';
        session()->flash('message', $message);

        return redirect()->route('company.settings');
    }

    public function render()
    {
        return view('livewire.company.company-create')
            ->layout('components.layouts.app');
    }
}
