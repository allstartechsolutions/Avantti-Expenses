<?php

namespace App\Livewire\Company;

use App\Livewire\Concerns\AuthorizesAbility;
use App\Models\Company;
use Livewire\Component;
use Livewire\WithFileUploads;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class CompanyInfo extends Component
{
    use AuthorizesAbility;

    use WithFileUploads;

    public ?Company $company = null;
    public $name = '';
    public $brand_name = '';
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

    /**
     * The three marks that appear on screen rather than on paper: the square
     * icon in the header, sidebar and login card, its dark-mode twin, and the
     * browser-tab favicon. Each is optional — whatever is left empty falls
     * back to the product's own icon, so this screen can be ignored entirely.
     */
    public $app_icon;
    public $appIconPreview;
    public $existingAppIcon = null;

    public $app_icon_dark;
    public $appIconDarkPreview;
    public $existingAppIconDark = null;

    public $favicon;
    public $faviconPreview;
    public $existingFavicon = null;

    /** The upload fields this screen owns, and where each one is stored. */
    private const BRAND_FILES = [
        'logo' => ['column' => 'logo', 'existing' => 'existingLogo', 'preview' => 'logoPreview', 'folder' => 'company-logos'],
        'app_icon' => ['column' => 'app_icon', 'existing' => 'existingAppIcon', 'preview' => 'appIconPreview', 'folder' => 'company-branding'],
        'app_icon_dark' => ['column' => 'app_icon_dark', 'existing' => 'existingAppIconDark', 'preview' => 'appIconDarkPreview', 'folder' => 'company-branding'],
        'favicon' => ['column' => 'favicon', 'existing' => 'existingFavicon', 'preview' => 'faviconPreview', 'folder' => 'company-branding'],
    ];

    protected $rules = [
        'name' => 'required|string|max:255',
        'brand_name' => 'nullable|string|max:60',
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
        'app_icon' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:1024',
        'app_icon_dark' => 'nullable|image|mimes:png,jpg,jpeg,webp|max:1024',
        // Not `image`: that rule rejects .ico, which is still the only format
        // every browser is guaranteed to accept for a favicon.
        'favicon' => 'nullable|file|mimes:ico,png|max:512',
    ];

    public function validationAttributes()
    {
        return [
            'name' => __('company name'),
            'brand_name' => __('display name'),
            'postal_code' => __('postal code'),
            'app_icon' => __('app icon'),
            'app_icon_dark' => __('dark mode icon'),
            'favicon' => __('favicon'),
        ];
    }

    public function mount()
    {
        $this->authorizeAbility('company.view');

        // Load existing company if it exists (should only be one)
        $this->company = Company::first();

        if ($this->company) {
            $this->name = $this->company->name;
            $this->brand_name = $this->company->brand_name;
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
            $this->existingAppIcon = $this->company->app_icon;
            $this->existingAppIconDark = $this->company->app_icon_dark;
            $this->existingFavicon = $this->company->favicon;
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

    public function updatedAppIcon()
    {
        $this->previewUpload('app_icon');
    }

    public function updatedAppIconDark()
    {
        $this->previewUpload('app_icon_dark');
    }

    public function updatedFavicon()
    {
        $this->previewUpload('favicon');
    }

    /**
     * Show what was just dropped, before it is saved. A rejected file leaves no
     * preview behind — the drop zone would otherwise show a picture of a file
     * the save is about to refuse.
     *
     * `.ico` is the catch: Livewire refuses to make a preview URL for it and
     * throws, so a perfectly valid favicon would take the screen down. The tile
     * says "ready to save" without a thumbnail in that case.
     */
    private function previewUpload(string $field): void
    {
        $config = self::BRAND_FILES[$field];

        $this->{$config['preview']} = null;
        $this->validateOnly($field);

        $this->{$config['preview']} = $this->{$field} && $this->{$field}->isPreviewable()
            ? $this->{$field}->temporaryUrl()
            : null;
    }

    /** Drop a file that has been chosen but not yet saved. */
    public function discardUpload(string $field)
    {
        abort_unless(isset(self::BRAND_FILES[$field]), 404);

        $config = self::BRAND_FILES[$field];

        $this->{$field} = null;
        $this->{$config['preview']} = null;
        $this->resetErrorBag($field);
    }

    /** Remove a mark that is already saved, so this install falls back again. */
    public function removeStoredFile(string $field)
    {
        $this->authorizeAbility('company.edit');

        abort_unless(isset(self::BRAND_FILES[$field]), 404);

        $config = self::BRAND_FILES[$field];
        $column = $config['column'];

        if ($this->company && $this->company->{$column}) {
            Storage::disk('public')->delete($this->company->{$column});
            $this->company->{$column} = null;
            $this->company->save();
            $this->{$config['existing']} = null;

            session()->flash('message', $field === 'logo'
                ? __('Logo removed successfully!')
                : __('Image removed. The default is being used again.'));
        }
    }

    public function removeLogo()
    {
        $this->discardUpload('logo');
    }

    public function removeExistingLogo()
    {
        $this->removeStoredFile('logo');
    }

    public function saveCompany()
    {
        $this->authorizeAbility('company.edit');

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
        $company->brand_name = $this->brand_name ?: null;
        $company->street = $this->street;
        $company->city = $this->city;
        $company->state = $this->state;
        $company->postal_code = $this->postal_code;
        $company->email = $this->email;
        $company->website = $this->website;
        $company->phone = $this->phone;
        $company->mobile = $this->mobile;
        $company->fax = $this->fax;

        foreach (self::BRAND_FILES as $field => $config) {
            if (! $this->{$field}) {
                continue;
            }

            // Replace rather than accumulate: the old file is nobody's once the
            // column stops pointing at it.
            if ($company->{$config['column']}) {
                Storage::disk('public')->delete($company->{$config['column']});
            }

            $company->{$config['column']} = $this->{$field}->store($config['folder'], 'public');
        }

        $company->save();

        $message = $this->company ? __('Company updated successfully!') : __('Company created successfully!');
        session()->flash('message', $message);

        return redirect()->route('company.info');
    }

    public function render()
    {
        return view('livewire.company.company-info')
            ->layout('components.layouts.app');
    }
}
