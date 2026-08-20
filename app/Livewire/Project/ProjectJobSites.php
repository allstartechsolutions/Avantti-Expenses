<?php

namespace App\Livewire\Project;

use App\Livewire\Concerns\AuthorizesAbility;
use App\Enums\JobSiteStatus;
use App\Enums\UserStatus;
use App\Models\ChangeOrder;
use App\Models\DailyReport;
use App\Models\DailyReportImage;
use App\Models\DailyReportManpower;
use App\Models\Expense;
use App\Models\JobSite;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;

class ProjectJobSites extends Component
{
    use AuthorizesAbility;

    public Project $project;

    // Search
    public $jobSiteSearch = '';

    // Delete Job Site modal
    public $showDeleteJobSiteModal = false;
    public $deletingJobSiteId = null;
    public $deleteJobSiteData = [];

    // Form properties
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
    public $supervisor_id = null;
    public $supervisor_change_note = '';

    protected function rules(): array
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
            'supervisor_id' => 'nullable|exists:users,id',
        ];
    }

    public function validationAttributes(): array
    {
        return [
            'job_site_name' => __('job site name'),
            'contact_person' => __('contact person'),
            'postal_code' => __('postal code'),
            'email' => __('email address'),
            'job_amount' => __('job amount'),
            'supervisor_id' => __('supervisor'),
        ];
    }

    public function mount(Project $project): void
    {
        $this->project = $project;
    }

    public function openJobSiteForm(): void
    {
        $this->authorizeAbility('project.edit', $this->project);

        $this->reset([
            'job_site_name', 'street', 'address_2', 'city', 'state', 'postal_code',
            'neighborhood', 'latitude', 'longitude', 'contact_person', 'phone',
            'email', 'job_amount', 'status', 'editingJobSite', 'supervisor_id',
            'supervisor_change_note'
        ]);

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

    public function editJobSite(int $jobSiteId): void
    {
        $this->authorizeAbility('project.edit', $this->project);

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
        $this->supervisor_id = $jobSite->supervisor_id;
        $this->supervisor_change_note = '';

        $this->showJobSiteForm = true;
    }

    public function saveJobSite()
    {
        $this->authorizeAbility('project.edit', $this->project);

        $this->validate();

        $supervisorId = $this->supervisor_id ?: null;

        if ($this->editingJobSite) {
            $jobSite = JobSite::findOrFail($this->editingJobSite);
            $oldSupervisorId = $jobSite->supervisor_id;

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
                'supervisor_id' => $supervisorId,
            ]);

            if ($oldSupervisorId !== $supervisorId) {
                $jobSite->recordSupervisorChange(
                    Auth::user(),
                    $oldSupervisorId,
                    $supervisorId,
                    $this->supervisor_change_note ?: null,
                );
            }

            session()->flash('message', __('Job site updated successfully!'));
            return $this->redirect(route('jobsites.overview', $jobSite), navigate: true);
        } else {
            $jobSite = JobSite::create([
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
                'supervisor_id' => $supervisorId,
            ]);

            if ($supervisorId) {
                $jobSite->recordSupervisorChange(
                    Auth::user(),
                    null,
                    $supervisorId,
                );
            }

            session()->flash('message', __('Job site created successfully!'));
            return $this->redirect(route('jobsites.overview', $jobSite), navigate: true);
        }
    }

    public function cancelJobSiteForm(): void
    {
        $this->showJobSiteForm = false;
        $this->reset([
            'job_site_name', 'street', 'address_2', 'city', 'state', 'postal_code',
            'neighborhood', 'latitude', 'longitude', 'contact_person', 'phone',
            'email', 'job_amount', 'status', 'editingJobSite', 'supervisor_id',
            'supervisor_change_note'
        ]);
    }

    public function confirmDeleteJobSite(int $jobSiteId): void
    {
        $this->authorizeAbility('projects.delete', $this->project);

        $jobSite = JobSite::withCount([
            'expenses',
            'changeOrders',
            'dailyReports',
        ])->findOrFail($jobSiteId);

        $hasBudget = $jobSite->budget()->exists() ? 1 : 0;

        $this->deletingJobSiteId = $jobSiteId;
        $this->deleteJobSiteData = [
            'name' => $jobSite->job_site_name,
            'expenses' => $jobSite->expenses_count,
            'change_orders' => $jobSite->change_orders_count,
            'daily_reports' => $jobSite->daily_reports_count,
            'budgets' => $hasBudget,
        ];

        $this->showDeleteJobSiteModal = true;
        $this->dispatch('open-modal', 'delete-jobsite-modal');
    }

    public function deleteJobSite(): void
    {
        $this->authorizeAbility('projects.delete', $this->project);

        $jobSite = JobSite::findOrFail($this->deletingJobSiteId);

        DB::transaction(function () use ($jobSite) {
            $this->cleanupJobSiteFiles($jobSite->id);
            $jobSite->delete();
        });

        $this->showDeleteJobSiteModal = false;
        $this->deletingJobSiteId = null;
        $this->deleteJobSiteData = [];

        session()->flash('message', __('Job site deleted successfully!'));
        $this->project->refresh();
    }

    public function cancelDeleteJobSite(): void
    {
        $this->showDeleteJobSiteModal = false;
        $this->deletingJobSiteId = null;
        $this->deleteJobSiteData = [];
        $this->dispatch('close-modal', 'delete-jobsite-modal');
    }

    protected function cleanupJobSiteFiles(int $jobSiteId): void
    {
        $receiptPaths = Expense::where('job_site_id', $jobSiteId)
            ->whereNotNull('receipt_path')
            ->pluck('receipt_path');

        foreach ($receiptPaths as $path) {
            Storage::delete($path);
        }

        $changeOrderPaths = ChangeOrder::where('job_site_id', $jobSiteId)
            ->whereNotNull('file_path')
            ->pluck('file_path');

        foreach ($changeOrderPaths as $path) {
            Storage::delete($path);
        }

        $dailyReportIds = DailyReport::where('job_site_id', $jobSiteId)->pluck('id');

        if ($dailyReportIds->isNotEmpty()) {
            $imagePaths = DailyReportImage::whereIn('imageable_id', $dailyReportIds)
                ->where('imageable_type', DailyReport::class)
                ->pluck('file_path');

            foreach ($imagePaths as $path) {
                Storage::delete($path);
            }

            $manpowerIds = DailyReportManpower::whereIn('daily_report_id', $dailyReportIds)->pluck('id');

            if ($manpowerIds->isNotEmpty()) {
                $manpowerImagePaths = DailyReportImage::whereIn('imageable_id', $manpowerIds)
                    ->where('imageable_type', DailyReportManpower::class)
                    ->pluck('file_path');

                foreach ($manpowerImagePaths as $path) {
                    Storage::delete($path);
                }
            }
        }
    }

    public function render()
    {
        $jobSitesQuery = $this->project->jobSites()->with(['createdBy', 'supervisor']);

        if ($this->jobSiteSearch) {
            $jobSitesQuery->where(function ($query) {
                $query->where('job_site_name', 'like', '%' . $this->jobSiteSearch . '%')
                    ->orWhere('contact_person', 'like', '%' . $this->jobSiteSearch . '%')
                    ->orWhere('email', 'like', '%' . $this->jobSiteSearch . '%')
                    ->orWhere('city', 'like', '%' . $this->jobSiteSearch . '%');
            });
        }

        $jobSites = $jobSitesQuery->orderBy('created_at', 'desc')->get();
        $statuses = JobSiteStatus::cases();
        $users = User::where('status', UserStatus::ACTIVE)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('livewire.project.project-job-sites', [
            'jobSites' => $jobSites,
            'statuses' => $statuses,
            'users' => $users,
        ])->layout('components.layouts.app');
    }
}
