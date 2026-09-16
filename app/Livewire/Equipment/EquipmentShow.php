<?php

namespace App\Livewire\Equipment;

use App\Livewire\Concerns\AuthorizesAbility;
use App\Models\Equipment;
use App\Models\EquipmentFinding;
use App\Models\EquipmentMaintenance;
use App\Models\EquipmentMaintenancePlan;
use App\Models\Expense;
use App\Models\JobSite;
use App\Models\Project;
use App\Models\Vendor;
use App\Services\BuyerDirectory;
use App\Services\MaintenanceScheduler;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * One piece of equipment, on tabs: overview, maintenance, readings,
 * findings, assignments, costs, attachments. Every dialog's state is a
 * public property on this component (the SubcontractorShow pattern); each
 * tab body large enough to deserve it is a partial.
 */
class EquipmentShow extends Component
{
    use AuthorizesAbility;
    use WithFileUploads;

    public Equipment $equipment;

    #[Url(except: 'overview', as: 'tab')]
    public string $activeTab = 'overview';

    public bool $showDeleteModal = false;

    // Assign dialog
    public bool $showAssignModal = false;

    public string $assign_project_id = '';

    public string $assign_job_site_id = '';

    public string $assign_responsible_user_id = '';

    public string $assign_started_at = '';

    public string $assign_notes = '';

    public function mount(Equipment $equipment): void
    {
        $this->authorizeAbility('equipment.view');

        $this->equipment = $equipment;

        if (! in_array($this->activeTab, $this->tabs(), true)) {
            $this->activeTab = 'overview';
        }
    }

    /** The tabs built so far; later phases add theirs. */
    public function tabs(): array
    {
        return ['overview', 'maintenance', 'readings', 'findings', 'assignments', 'costs', 'attachments'];
    }

    public function setActiveTab(string $tab): void
    {
        $this->activeTab = in_array($tab, $this->tabs(), true) ? $tab : 'overview';
    }

    /*
    |---------------------------------------------------------------------------
    | Readings
    |---------------------------------------------------------------------------
    */

    public bool $showReadingModal = false;

    public string $reading_value = '';

    public string $reading_date = '';

    public string $reading_notes = '';

    public function startReading(): void
    {
        $this->authorizeAbility('equipment.maintain');

        $this->resetValidation();
        $this->reading_value = '';
        $this->reading_date = now()->format('Y-m-d');
        $this->reading_notes = '';
        $this->showReadingModal = true;
    }

    public function cancelReading(): void
    {
        $this->showReadingModal = false;
    }

    public function saveReading(): void
    {
        $this->authorizeAbility('equipment.maintain');

        $this->validate([
            'reading_value' => ['required', 'numeric', 'min:0'],
            'reading_date' => ['required', 'date', 'before_or_equal:today'],
            'reading_notes' => ['nullable', 'string', 'max:255'],
        ], [], ['reading_value' => __('reading'), 'reading_date' => __('reading date')]);

        try {
            $result = app(MaintenanceScheduler::class)->logReading($this->equipment, (float) $this->reading_value, $this->reading_date, trim($this->reading_notes) ?: null);
        } catch (ValidationException $e) {
            $this->addError('reading_value', $e->errors()['reading'][0] ?? __('The reading was refused.'));

            return;
        }

        $this->showReadingModal = false;
        $this->equipment->refresh();

        session()->flash('message', $result['nowDue']->isEmpty()
            ? __('Reading logged.')
            : __('Reading logged. Now due: :titles', ['titles' => $result['nowDue']->pluck('title')->implode(', ')]));
    }

    /*
    |---------------------------------------------------------------------------
    | Plans
    |---------------------------------------------------------------------------
    */

    public bool $showPlanModal = false;

    public ?int $editingPlanId = null;

    public string $plan_title = '';

    public string $plan_type = 'preventive';

    public string $plan_interval_days = '';

    public string $plan_interval_meter = '';

    public string $plan_meter_lead = '';

    public string $plan_notes = '';

    public function startPlan(?int $planId = null): void
    {
        $this->authorizeAbility('equipment.maintain');

        $this->resetValidation();
        $this->editingPlanId = null;
        $this->plan_title = '';
        $this->plan_type = 'preventive';
        $this->plan_interval_days = '';
        $this->plan_interval_meter = '';
        $this->plan_meter_lead = '';
        $this->plan_notes = '';

        if ($planId) {
            $plan = $this->equipment->plans()->findOrFail($planId);
            $this->editingPlanId = $plan->id;
            $this->plan_title = $plan->title;
            $this->plan_type = $plan->maintenance_type;
            $this->plan_interval_days = (string) ($plan->interval_days ?? '');
            $this->plan_interval_meter = (string) ($plan->interval_meter ?? '');
            $this->plan_meter_lead = (string) ($plan->meter_lead ?? '');
            $this->plan_notes = (string) $plan->notes;
        }

        $this->showPlanModal = true;
    }

    public function cancelPlan(): void
    {
        $this->showPlanModal = false;
    }

    public function savePlan(): void
    {
        $this->authorizeAbility('equipment.maintain');

        $this->validate([
            'plan_title' => ['required', 'string', 'max:150'],
            'plan_type' => ['required', Rule::in(EquipmentMaintenancePlan::TYPES)],
            'plan_interval_days' => ['nullable', 'integer', 'min:1', 'max:3650', 'required_without:plan_interval_meter'],
            'plan_interval_meter' => ['nullable', 'integer', 'min:1', 'required_without:plan_interval_days'],
            'plan_meter_lead' => ['nullable', 'integer', 'min:0'],
            'plan_notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'plan_interval_days.required_without' => __('Give an interval in days, in meter units, or both.'),
            'plan_interval_meter.required_without' => __('Give an interval in days, in meter units, or both.'),
        ], ['plan_title' => __('title'), 'plan_type' => __('type'), 'plan_interval_days' => __('interval (days)'), 'plan_interval_meter' => __('interval (meter)'), 'plan_meter_lead' => __('meter margin')]);

        if ($this->plan_interval_meter !== '' && ! $this->equipment->hasMeter()) {
            $this->addError('plan_interval_meter', __('This equipment has no meter; give the interval in days.'));

            return;
        }

        $data = [
            'title' => trim($this->plan_title),
            'maintenance_type' => $this->plan_type,
            'interval_days' => $this->plan_interval_days !== '' ? (int) $this->plan_interval_days : null,
            'interval_meter' => $this->plan_interval_meter !== '' ? (int) $this->plan_interval_meter : null,
            'meter_lead' => $this->plan_meter_lead !== '' ? (int) $this->plan_meter_lead : null,
            'notes' => trim($this->plan_notes) ?: null,
        ];

        $scheduler = app(MaintenanceScheduler::class);

        if ($this->editingPlanId) {
            $scheduler->updatePlan($this->equipment->plans()->findOrFail($this->editingPlanId), $data);
            session()->flash('message', __('Plan updated and the next service re-planned.'));
        } else {
            $scheduler->addPlan($this->equipment, $data);
            session()->flash('message', __('Plan added; its first service is scheduled.'));
        }

        $this->showPlanModal = false;
        $this->equipment->refresh();
    }

    public function togglePlan(int $planId): void
    {
        $this->authorizeAbility('equipment.maintain');

        $plan = $this->equipment->plans()->findOrFail($planId);
        $scheduler = app(MaintenanceScheduler::class);

        $plan->is_active ? $scheduler->deactivatePlan($plan) : $scheduler->reactivatePlan($plan);

        session()->flash('message', $plan->fresh()->is_active ? __('Plan reactivated.') : __('Plan deactivated; its pending service was cancelled.'));
    }

    /*
    |---------------------------------------------------------------------------
    | Maintenances — schedule, start, complete, cancel
    |---------------------------------------------------------------------------
    */

    public bool $showScheduleModal = false;

    public string $m_title = '';

    public string $m_type = 'corrective';

    public string $m_description = '';

    public string $m_scheduled_date = '';

    public string $m_due_meter = '';

    public bool $showCompleteModal = false;

    public ?int $completingId = null;

    public string $c_completed_date = '';

    public string $c_meter = '';

    public string $c_performed_by = 'internal';

    public string $c_supplier_id = '';

    public string $c_performed_by_user_id = '';

    public string $c_notes = '';

    public array $c_resolve = [];

    public bool $showCancelModal = false;

    public ?int $cancellingId = null;

    public string $cancel_reason = '';

    public function startSchedule(): void
    {
        $this->authorizeAbility('equipment.maintain');

        $this->resetValidation();
        $this->m_title = '';
        $this->m_type = 'corrective';
        $this->m_description = '';
        $this->m_scheduled_date = now()->format('Y-m-d');
        $this->m_due_meter = '';
        $this->showScheduleModal = true;
    }

    public function cancelSchedule(): void
    {
        $this->showScheduleModal = false;
    }

    public function saveSchedule(): void
    {
        $this->authorizeAbility('equipment.maintain');

        $this->validate([
            'm_title' => ['required', 'string', 'max:150'],
            'm_type' => ['required', Rule::in(EquipmentMaintenance::TYPES)],
            'm_description' => ['nullable', 'string', 'max:5000'],
            'm_scheduled_date' => ['nullable', 'date', 'required_without:m_due_meter'],
            'm_due_meter' => ['nullable', 'numeric', 'min:0', 'required_without:m_scheduled_date'],
        ], [
            'm_scheduled_date.required_without' => __('Give a date, a meter reading, or both.'),
            'm_due_meter.required_without' => __('Give a date, a meter reading, or both.'),
        ], ['m_title' => __('title'), 'm_type' => __('type'), 'm_scheduled_date' => __('date'), 'm_due_meter' => __('due reading')]);

        app(MaintenanceScheduler::class)->schedule($this->equipment, [
            'title' => trim($this->m_title),
            'maintenance_type' => $this->m_type,
            'description' => trim($this->m_description) ?: null,
            'scheduled_date' => $this->m_scheduled_date ?: null,
            'due_meter' => $this->m_due_meter !== '' && $this->equipment->hasMeter() ? (float) $this->m_due_meter : null,
        ]);

        $this->showScheduleModal = false;
        session()->flash('message', __('Maintenance scheduled.'));
    }

    public function startMaintenance(int $maintenanceId): void
    {
        $this->authorizeAbility('equipment.maintain');

        $maintenance = $this->equipment->maintenances()->findOrFail($maintenanceId);

        try {
            app(MaintenanceScheduler::class)->start($maintenance);
        } catch (ValidationException $e) {
            session()->flash('error', $e->errors()['maintenance'][0]);

            return;
        }

        $this->equipment->refresh();
        session()->flash('message', __('Maintenance started; the equipment is marked in maintenance.'));
    }

    public function startComplete(int $maintenanceId): void
    {
        $this->authorizeAbility('equipment.maintain');

        $maintenance = $this->equipment->maintenances()->findOrFail($maintenanceId);

        $this->resetValidation();
        $this->completingId = $maintenance->id;
        $this->c_completed_date = now()->format('Y-m-d');
        $this->c_meter = $this->equipment->current_meter !== null ? (string) $this->equipment->current_meter : '';
        $this->c_performed_by = $maintenance->supplier_id ? 'vendor' : 'internal';
        $this->c_supplier_id = (string) ($maintenance->supplier_id ?? '');
        $this->c_performed_by_user_id = (string) auth()->id();
        $this->c_notes = '';
        $this->c_resolve = [];
        $this->showCompleteModal = true;
    }

    public function cancelComplete(): void
    {
        $this->showCompleteModal = false;
        $this->completingId = null;
    }

    public function saveComplete(): void
    {
        $this->authorizeAbility('equipment.maintain');

        $maintenance = $this->equipment->maintenances()->findOrFail((int) $this->completingId);

        $this->validate([
            'c_completed_date' => ['required', 'date', 'before_or_equal:today'],
            'c_meter' => [$this->equipment->hasMeter() ? 'required' : 'nullable', 'numeric', 'min:0'],
            'c_performed_by' => ['required', Rule::in(['internal', 'vendor'])],
            'c_supplier_id' => ['nullable', 'required_if:c_performed_by,vendor', Rule::exists('vendors', 'id')->where('is_supplier', 1)],
            'c_performed_by_user_id' => ['nullable', Rule::exists('users', 'id')],
            'c_notes' => ['nullable', 'string', 'max:5000'],
            'c_resolve' => ['array'],
            'c_resolve.*' => ['integer'],
        ], [], ['c_completed_date' => __('completion date'), 'c_meter' => __('meter at completion'), 'c_performed_by' => __('performed by'), 'c_supplier_id' => __('vendor'), 'c_performed_by_user_id' => __('person')]);

        try {
            app(MaintenanceScheduler::class)->complete($maintenance, [
                'completed_date' => $this->c_completed_date,
                'meter_at_completion' => $this->c_meter !== '' ? (float) $this->c_meter : null,
                'performed_by' => $this->c_performed_by,
                'supplier_id' => $this->c_supplier_id !== '' ? (int) $this->c_supplier_id : null,
                'performed_by_user_id' => $this->c_performed_by_user_id !== '' ? (int) $this->c_performed_by_user_id : null,
                'completion_notes' => trim($this->c_notes) ?: null,
            ], array_map('intval', $this->c_resolve));
        } catch (ValidationException $e) {
            $errors = $e->errors();
            $this->addError('c_meter', $errors['reading'][0] ?? $errors['maintenance'][0] ?? __('The completion was refused.'));

            return;
        }

        $this->showCompleteModal = false;
        $this->completingId = null;
        $this->equipment->refresh();
        session()->flash('message', __('Maintenance completed.'));
    }

    public function startCancel(int $maintenanceId): void
    {
        $this->authorizeAbility('equipment.maintain');

        $this->cancellingId = $this->equipment->maintenances()->findOrFail($maintenanceId)->id;
        $this->cancel_reason = '';
        $this->resetValidation();
        $this->showCancelModal = true;
    }

    public function cancelCancel(): void
    {
        $this->showCancelModal = false;
        $this->cancellingId = null;
    }

    public function saveCancel(): void
    {
        $this->authorizeAbility('equipment.maintain');

        $this->validate(['cancel_reason' => ['required', 'string', 'max:255']], [], ['cancel_reason' => __('reason')]);

        $maintenance = $this->equipment->maintenances()->findOrFail((int) $this->cancellingId);

        try {
            app(MaintenanceScheduler::class)->cancel($maintenance, trim($this->cancel_reason));
        } catch (ValidationException $e) {
            session()->flash('error', $e->errors()['maintenance'][0]);
            $this->showCancelModal = false;
            $this->cancellingId = null;

            return;
        }

        $this->showCancelModal = false;
        $this->cancellingId = null;
        $this->equipment->refresh();
        session()->flash('message', __('Maintenance cancelled.'));
    }

    /*
    |---------------------------------------------------------------------------
    | Findings
    |---------------------------------------------------------------------------
    */

    public bool $showFindingModal = false;

    public string $f_maintenance_id = '';

    public string $f_description = '';

    public string $f_severity = 'medium';

    public $f_photo = null;

    public bool $showResolveModal = false;

    public ?int $resolvingId = null;

    public string $r_notes = '';

    public function startFinding(?int $maintenanceId = null): void
    {
        $this->authorizeAbility('equipment.maintain');

        $this->resetValidation();
        $this->f_maintenance_id = (string) ($maintenanceId ?? '');
        $this->f_description = '';
        $this->f_severity = 'medium';
        $this->f_photo = null;
        $this->showFindingModal = true;
    }

    public function cancelFinding(): void
    {
        $this->f_photo?->delete();
        $this->f_photo = null;
        $this->showFindingModal = false;
    }

    public function clearFindingPhoto(): void
    {
        $this->authorizeAbility('equipment.maintain');

        $this->f_photo?->delete();
        $this->f_photo = null;
    }

    public function saveFinding(): void
    {
        $this->authorizeAbility('equipment.maintain');

        $this->validate([
            'f_maintenance_id' => ['required', Rule::exists('equipment_maintenances', 'id')->where('equipment_id', $this->equipment->id)],
            'f_description' => ['required', 'string', 'max:2000'],
            'f_severity' => ['required', Rule::in(EquipmentFinding::SEVERITIES)],
            'f_photo' => ['nullable', 'image', 'max:10240'],
        ], [], ['f_maintenance_id' => __('maintenance'), 'f_description' => __('description'), 'f_severity' => __('severity'), 'f_photo' => __('photo')]);

        $maintenance = $this->equipment->maintenances()->findOrFail((int) $this->f_maintenance_id);

        try {
            app(MaintenanceScheduler::class)->recordFinding(
                $maintenance,
                trim($this->f_description),
                $this->f_severity,
                $this->f_photo?->store('equipment/findings', 'local'),
            );
        } catch (ValidationException $e) {
            $this->addError('f_maintenance_id', $e->errors()['finding'][0]);

            return;
        }

        $this->f_photo = null;
        $this->showFindingModal = false;
        session()->flash('message', __('Finding recorded.'));
    }

    public function startResolve(int $findingId): void
    {
        $this->authorizeAbility('equipment.maintain');

        $this->resolvingId = $this->equipment->findings()->open()->findOrFail($findingId)->id;
        $this->r_notes = '';
        $this->resetValidation();
        $this->showResolveModal = true;
    }

    public function cancelResolve(): void
    {
        $this->showResolveModal = false;
        $this->resolvingId = null;
    }

    public function saveResolve(): void
    {
        $this->authorizeAbility('equipment.maintain');

        $this->validate(['r_notes' => ['required', 'string', 'max:2000']], [], ['r_notes' => __('resolution')]);

        $finding = $this->equipment->findings()->open()->findOrFail((int) $this->resolvingId);
        app(MaintenanceScheduler::class)->resolveFinding($finding, trim($this->r_notes));

        $this->showResolveModal = false;
        $this->resolvingId = null;
        session()->flash('message', __('Finding resolved.'));
    }

    /**
     * The Costs tab: every expense tagged to this equipment that the reader
     * may see, by category or project, by month, by maintenance, and the
     * total cost of ownership over the same narrowed set.
     */
    protected function costsData(): array
    {
        $user = auth()->user();

        $expenses = $this->equipment->visibleExpenses($user)
            ->with(['category', 'project:id,project_name', 'jobSite:id,job_site_name', 'supplier:id,name', 'equipmentMaintenance:id,title', 'items'])
            ->orderByDesc('expense_date')
            ->get();

        $total = round($expenses->sum('total_amount'), 2);

        $byBucket = $expenses
            ->groupBy(fn (Expense $e) => $e->isCompanyLevel()
                ? 'c'.($e->expense_category_id ?? 0)
                : 'p'.$e->project_id)
            ->map(fn ($rows) => [
                'label' => $rows->first()->isCompanyLevel()
                    ? ($rows->first()->category?->getDisplayLabel() ?? __('Company (general)'))
                    : ($rows->first()->project?->project_name ?? '—'),
                'kind' => $rows->first()->isCompanyLevel() ? 'company' : 'project',
                'count' => $rows->count(),
                'total' => round($rows->sum('total_amount'), 2),
            ])
            ->sortByDesc('total')
            ->values();

        $byMonth = $expenses
            ->groupBy(fn (Expense $e) => $e->expense_date->format('Y-m'))
            ->map(fn ($rows, $month) => [
                'month' => $month,
                'count' => $rows->count(),
                'total' => round($rows->sum('total_amount'), 2),
            ])
            ->sortKeysDesc()
            ->values();

        $byMaintenance = $expenses
            ->whereNotNull('equipment_maintenance_id')
            ->groupBy('equipment_maintenance_id')
            ->map(fn ($rows) => [
                'title' => $rows->first()->equipmentMaintenance?->title ?? '—',
                'count' => $rows->count(),
                'total' => round($rows->sum('total_amount'), 2),
            ])
            ->sortByDesc('total')
            ->values();

        return [
            'expenses' => $expenses,
            'total' => $total,
            'purchase' => (float) ($this->equipment->purchase_cost ?? 0),
            'tco' => round((float) ($this->equipment->purchase_cost ?? 0) + $total, 2),
            'byBucket' => $byBucket,
            'byMonth' => $byMonth,
            'byMaintenance' => $byMaintenance,
            'untagged' => round($expenses->whereNull('equipment_maintenance_id')->sum('total_amount'), 2),
            'canFileCompany' => $this->allowsAbility('company-expenses.create'),
        ];
    }

    public function getSuppliersProperty()
    {
        return Vendor::where('is_supplier', true)->orderBy('name')->get(['id', 'name']);
    }

    /*
    |---------------------------------------------------------------------------
    | Assignments
    |---------------------------------------------------------------------------
    */

    public function startAssign(): void
    {
        $this->authorizeAbility('equipment.assign');

        $this->resetValidation();
        $this->assign_project_id = (string) ($this->equipment->project_id ?? '');
        $this->assign_job_site_id = (string) ($this->equipment->job_site_id ?? '');
        $this->assign_responsible_user_id = (string) ($this->equipment->responsible_user_id ?? '');
        $this->assign_started_at = now()->format('Y-m-d');
        $this->assign_notes = '';
        $this->showAssignModal = true;
    }

    public function cancelAssign(): void
    {
        $this->showAssignModal = false;
    }

    public function updatedAssignProjectId(): void
    {
        $this->assign_job_site_id = '';
    }

    public function assign(): void
    {
        $this->authorizeAbility('equipment.assign');

        // Only a project the actor may open: the dropdown is narrowed the same
        // way, and a confined member posting another id gets the same answer.
        $visibleProjects = Project::visibleTo(auth()->user())->pluck('id')->all();

        $this->validate([
            'assign_project_id' => ['nullable', Rule::in($visibleProjects)],
            'assign_job_site_id' => [
                'nullable',
                // A job site of THE CHOSEN project and no other.
                Rule::exists('job_sites', 'id')->where('project_id', (int) $this->assign_project_id),
            ],
            'assign_responsible_user_id' => ['nullable', Rule::exists('users', 'id')],
            'assign_started_at' => ['required', 'date'],
            'assign_notes' => ['nullable', 'string', 'max:255'],
        ], [], [
            'assign_project_id' => __('project'),
            'assign_job_site_id' => __('job site'),
        ]);

        $this->validate([
            'assign_job_site_id' => ['nullable', Rule::exists('job_sites', 'id')->whereIn('project_id', $visibleProjects)],
        ], [], [
            'assign_job_site_id' => __('job site'),
            'assign_responsible_user_id' => __('responsible person'),
            'assign_started_at' => __('start date'),
        ]);

        if ($this->assign_project_id === '' && $this->assign_job_site_id === '' && $this->assign_responsible_user_id === '') {
            $this->addError('assign_project_id', __('Choose a project, a job site or a person.'));

            return;
        }

        $this->equipment->assignTo(
            $this->assign_project_id !== '' ? (int) $this->assign_project_id : null,
            $this->assign_job_site_id !== '' ? (int) $this->assign_job_site_id : null,
            $this->assign_responsible_user_id !== '' ? (int) $this->assign_responsible_user_id : null,
            $this->assign_started_at,
            trim($this->assign_notes) ?: null,
        );

        $this->showAssignModal = false;
        $this->equipment->refresh();
        session()->flash('message', __('Assignment recorded.'));
    }

    public function endAssignment(): void
    {
        $this->authorizeAbility('equipment.assign');

        $this->equipment->endAssignment();
        $this->equipment->refresh();

        session()->flash('message', __('Sent back: the equipment is unassigned.'));
    }

    public function getAssignProjectsProperty()
    {
        return Project::visibleTo(auth()->user())->orderBy('project_name')->get(['id', 'project_name']);
    }

    public function getAssignJobSitesProperty()
    {
        if ($this->assign_project_id === '') {
            return collect();
        }

        return JobSite::where('project_id', (int) $this->assign_project_id)->orderBy('job_site_name')->get(['id', 'job_site_name']);
    }

    public function getAssignPeopleProperty()
    {
        return app(BuyerDirectory::class)->activeStaff()->orderBy('name')->get(['id', 'name']);
    }

    /*
    |---------------------------------------------------------------------------
    | Delete
    |---------------------------------------------------------------------------
    */

    public function confirmDelete(): void
    {
        $this->authorizeAbility('equipment.delete');

        $this->showDeleteModal = true;
    }

    public function cancelDelete(): void
    {
        $this->showDeleteModal = false;
    }

    /** What goes with it, for the confirmation. */
    public function deleteCounts(): array
    {
        return [
            'readings' => $this->equipment->readings()->count(),
            'assignments' => $this->equipment->assignments()->count(),
            'plans' => $this->equipment->plans()->count(),
            'maintenances' => $this->equipment->maintenances()->count(),
            'findings' => $this->equipment->findings()->count(),
            'attachments' => $this->equipment->attachments()->count(),
        ];
    }

    public function delete()
    {
        $this->authorizeAbility('equipment.delete');

        if ($this->equipment->deleteBlockers() !== []) {
            session()->flash('error', __('This equipment has expenses tagged to it and cannot be deleted. Retire it instead.'));
            $this->showDeleteModal = false;

            return null;
        }

        DB::transaction(function () {
            // Files first: the cascade would leave them on disk.
            if ($this->equipment->photo_path) {
                Storage::delete($this->equipment->photo_path);
            }

            foreach ($this->equipment->findings()->whereNotNull('photo_path')->pluck('photo_path') as $path) {
                Storage::delete($path);
            }

            foreach ($this->equipment->attachments as $attachment) {
                $attachment->delete();
            }

            $this->equipment->delete();
        });

        session()->flash('message', __('Equipment deleted.'));

        return redirect()->route('equipment.index');
    }

    public function render()
    {
        $this->equipment->load(['supplier', 'project', 'jobSite', 'responsible', 'createdBy']);

        return view('livewire.equipment.equipment-show', [
            'nextMaintenance' => $this->equipment->nextMaintenance(),
            'openFindings' => $this->equipment->findings()->open()->count(),
            'lastReading' => $this->equipment->readings()->first(),
            'histories' => $this->equipment->histories()->with('changedBy')->limit(50)->get(),
            'plans' => $this->activeTab === 'maintenance' ? $this->equipment->plans()->with('openOccurrence')->get() : collect(),
            'openMaintenances' => in_array($this->activeTab, ['maintenance', 'findings'], true)
                ? $this->equipment->maintenances()->open()->with(['plan', 'supplier:id,name'])->orderByRaw('CASE WHEN scheduled_date IS NULL THEN 1 ELSE 0 END')->orderBy('scheduled_date')->get()->each(fn ($m) => $m->setRelation('equipment', $this->equipment))
                : collect(),
            'costByMaintenance' => $this->activeTab === 'maintenance'
                ? $this->equipment->visibleExpenses(auth()->user())->whereNotNull('equipment_maintenance_id')
                    ->groupBy('equipment_maintenance_id')->selectRaw('equipment_maintenance_id, SUM(total_amount) as cents')
                    ->pluck('cents', 'equipment_maintenance_id')
                : collect(),
            'closedMaintenances' => $this->activeTab === 'maintenance'
                ? $this->equipment->maintenances()->whereIn('status', ['completed', 'cancelled'])->with(['plan', 'supplier:id,name', 'performedByUser:id,name', 'completedBy:id,name'])->withCount('findings')->orderByDesc('completed_date')->orderByDesc('updated_at')->limit(100)->get()->each(fn ($m) => $m->setRelation('equipment', $this->equipment))
                : collect(),
            'readings' => $this->activeTab === 'readings' ? $this->equipment->readings()->with(['recordedBy:id,name', 'maintenance:id,title'])->limit(200)->get() : collect(),
            'findings' => $this->activeTab === 'findings'
                ? $this->equipment->findings()->with(['maintenance:id,title,completed_date', 'resolvedIn:id,title', 'reportedBy:id,name', 'resolvedBy:id,name'])->get()
                : collect(),
            'findingTargets' => in_array($this->activeTab, ['findings', 'maintenance'], true)
                ? $this->equipment->maintenances()->whereIn('status', ['in_progress', 'completed'])->orderByDesc('completed_date')->orderByDesc('started_at')->limit(30)->get(['id', 'title', 'status', 'completed_date'])
                : collect(),
            'openFindingsList' => $this->showCompleteModal ? $this->equipment->findings()->open()->get() : collect(),
            'costs' => $this->activeTab === 'costs' ? $this->costsData() : [],
            'assignments' => $this->activeTab === 'assignments'
                ? $this->equipment->assignments()->with(['project:id,project_name', 'jobSite:id,job_site_name', 'responsible:id,name', 'assignedBy:id,name', 'endedBy:id,name'])->get()
                : collect(),
            'deleteCounts' => $this->showDeleteModal ? $this->deleteCounts() : [],
        ])->layout('components.layouts.app');
    }
}
