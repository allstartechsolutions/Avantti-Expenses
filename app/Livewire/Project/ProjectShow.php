<?php

namespace App\Livewire\Project;

use App\Enums\JobSiteStatus;
use App\Models\JobSite;
use App\Models\Project;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class ProjectShow extends Component
{
    public Project $project;
    public $activeTab = 'overview';

    // Job Site search and filters
    public $jobSiteSearch = '';

    // Job Site form properties
    public $showJobSiteForm = false;
    public $editingJobSite = null;
    public $job_site_name = '';
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
    public $job_amount = '';
    public $status = 'created';

    protected function rules()
    {
        return [
            'job_site_name' => 'required|string|max:255',
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
            'job_amount' => 'required|numeric|min:0',
            'status' => 'required|in:created,in_progress,completed,on_hold',
        ];
    }

    protected $validationAttributes = [
        'job_site_name' => 'job site name',
        'contact_person' => 'contact person',
        'postal_code' => 'postal code',
        'email' => 'email address',
        'job_amount' => 'job amount',
    ];

    public function mount(Project $project)
    {
        $this->project = $project;
    }

    public function setActiveTab($tab)
    {
        $this->activeTab = $tab;
    }

    public function openJobSiteForm()
    {
        $this->reset(['job_site_name', 'street', 'address_2', 'city', 'state', 'postal_code', 'neighborhood', 'latitude', 'longitude', 'contact_person', 'phone', 'email', 'job_amount', 'status', 'editingJobSite']);

        // Pre-populate with project data
        $this->street = $this->project->street ?? '';
        $this->address_2 = $this->project->address_2 ?? '';
        $this->city = $this->project->city ?? '';
        $this->state = $this->project->state ?? '';
        $this->postal_code = $this->project->postal_code ?? '';
        $this->neighborhood = $this->project->neighborhood ?? '';
        $this->latitude = $this->project->latitude;
        $this->longitude = $this->project->longitude;
        $this->contact_person = $this->project->contact_person;
        $this->phone = $this->project->phone ?? '';
        $this->email = $this->project->email;
        $this->status = 'created';

        $this->showJobSiteForm = true;
    }

    public function editJobSite($jobSiteId)
    {
        $jobSite = JobSite::findOrFail($jobSiteId);

        $this->editingJobSite = $jobSite->id;
        $this->job_site_name = $jobSite->job_site_name;
        $this->street = $jobSite->street;
        $this->address_2 = $jobSite->address_2;
        $this->city = $jobSite->city;
        $this->state = $jobSite->state;
        $this->postal_code = $jobSite->postal_code;
        $this->neighborhood = $jobSite->neighborhood;
        $this->latitude = $jobSite->latitude;
        $this->longitude = $jobSite->longitude;
        $this->contact_person = $jobSite->contact_person;
        $this->phone = $jobSite->phone;
        $this->email = $jobSite->email;
        $this->job_amount = $jobSite->job_amount;
        $this->status = $jobSite->status->value;

        $this->showJobSiteForm = true;
    }

    public function saveJobSite()
    {
        $this->validate();

        if ($this->editingJobSite) {
            // Update existing job site
            $jobSite = JobSite::findOrFail($this->editingJobSite);
            $jobSite->update([
                'job_site_name' => $this->job_site_name,
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
                'job_amount' => $this->job_amount,
                'status' => $this->status,
            ]);

            session()->flash('message', 'Job site updated successfully!');
        } else {
            // Create new job site
            JobSite::create([
                'project_id' => $this->project->id,
                'job_site_name' => $this->job_site_name,
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
                'job_amount' => $this->job_amount,
                'status' => $this->status,
                'created_by' => Auth::id(),
            ]);

            session()->flash('message', 'Job site created successfully!');
        }

        $this->showJobSiteForm = false;
        $this->project->refresh();
    }

    public function cancelJobSiteForm()
    {
        $this->showJobSiteForm = false;
        $this->reset(['job_site_name', 'street', 'address_2', 'city', 'state', 'postal_code', 'neighborhood', 'latitude', 'longitude', 'contact_person', 'phone', 'email', 'job_amount', 'status', 'editingJobSite']);
    }

    public function render()
    {
        $jobSitesQuery = $this->project->jobSites()->with('createdBy');

        // Apply search filter
        if ($this->jobSiteSearch) {
            $jobSitesQuery->where(function($query) {
                $query->where('job_site_name', 'like', '%' . $this->jobSiteSearch . '%')
                    ->orWhere('contact_person', 'like', '%' . $this->jobSiteSearch . '%')
                    ->orWhere('email', 'like', '%' . $this->jobSiteSearch . '%')
                    ->orWhere('city', 'like', '%' . $this->jobSiteSearch . '%');
            });
        }

        $jobSites = $jobSitesQuery->orderBy('created_at', 'desc')->get();
        $statuses = JobSiteStatus::cases();

        return view('livewire.project.project-show', [
            'jobSites' => $jobSites,
            'statuses' => $statuses,
        ])->layout('components.layouts.app');
    }
}

