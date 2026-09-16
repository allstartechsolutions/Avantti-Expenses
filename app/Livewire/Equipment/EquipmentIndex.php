<?php

namespace App\Livewire\Equipment;

use App\Livewire\Concerns\AuthorizesAbility;
use App\Models\Equipment;
use App\Models\EquipmentMaintenance;
use App\Models\Project;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/** The asset register: everything the company owns, leases or rents, and where it is. */
class EquipmentIndex extends Component
{
    use AuthorizesAbility;
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $typeFilter = '';

    #[Url(except: '')]
    public string $statusFilter = '';

    /** '' | unassigned | project:{id} | person */
    #[Url(except: '')]
    public string $assignmentFilter = '';

    public function mount(): void
    {
        $this->authorizeAbility('equipment.view');
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedTypeFilter(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedAssignmentFilter(): void
    {
        $this->resetPage();
    }

    public function hasFilters(): bool
    {
        return $this->search !== '' || $this->typeFilter !== '' || $this->statusFilter !== '' || $this->assignmentFilter !== '';
    }

    public function clearFilters(): void
    {
        $this->reset(['search', 'typeFilter', 'statusFilter', 'assignmentFilter']);
        $this->resetPage();
    }

    protected function counts(): array
    {
        $today = now();
        $open = EquipmentMaintenance::query()->open()->with(['equipment', 'plan'])->get();

        return [
            'total' => Equipment::inService()->count(),
            'active' => Equipment::where('status', 'active')->count(),
            'in_maintenance' => Equipment::where('status', 'in_maintenance')->count(),
            'overdue' => $open->filter(fn ($m) => $m->isOverdue($today) || $m->isDueByMeter())->pluck('equipment_id')->unique()->count(),
            'due_soon' => $open->filter(fn ($m) => $m->isDueSoon($today))->pluck('equipment_id')->unique()->count(),
        ];
    }

    /**
     * A bare entry — registered by mistake, nothing recorded under it — goes
     * from the list. Anything with history is deleted from its own page,
     * where the counts are shown, or retired when money is tagged to it.
     */
    public function delete(int $id): void
    {
        $this->authorizeAbility('equipment.delete');

        $equipment = Equipment::withCount(Equipment::RECORD_COUNTS)->findOrFail($id);

        if (! $equipment->hasNoRecords()) {
            session()->flash('error', __('This equipment has records under it. Delete it from its page, where they are listed.'));

            return;
        }

        if ($equipment->photo_path) {
            Storage::delete($equipment->photo_path);
        }

        $equipment->delete();

        session()->flash('message', __('Equipment deleted.'));
    }

    public function render()
    {
        $equipment = Equipment::query()
            ->with(['project:id,project_name', 'jobSite:id,job_site_name', 'responsible:id,name'])
            ->withCount(Equipment::RECORD_COUNTS)
            ->when($this->search !== '', fn ($q) => $q->search($this->search))
            ->when($this->typeFilter !== '', fn ($q) => $q->where('equipment_type', $this->typeFilter))
            ->when($this->statusFilter !== '', fn ($q) => $q->where('status', $this->statusFilter))
            ->when($this->statusFilter === '', fn ($q) => $q->inService())
            ->when($this->assignmentFilter === 'unassigned', fn ($q) => $q->whereNull('project_id')->whereNull('job_site_id')->whereNull('responsible_user_id'))
            ->when($this->assignmentFilter === 'person', fn ($q) => $q->whereNull('project_id')->whereNull('job_site_id')->whereNotNull('responsible_user_id'))
            ->when(str_starts_with($this->assignmentFilter, 'project:'), fn ($q) => $q->where('project_id', (int) substr($this->assignmentFilter, 8)))
            ->orderBy('name')
            ->paginate(25);

        // The next open maintenance per piece, one query for the page.
        $next = EquipmentMaintenance::query()
            ->open()
            ->whereIn('equipment_id', $equipment->pluck('id'))
            ->with('plan')
            ->orderByRaw('CASE WHEN scheduled_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('scheduled_date')
            ->get()
            ->groupBy('equipment_id')
            ->map(fn ($rows) => $rows->first());

        return view('livewire.equipment.equipment-index', [
            'equipment' => $equipment,
            'nextMaintenance' => $next,
            'counts' => $this->counts(),
            'projects' => Project::visibleTo(auth()->user())->orderBy('project_name')->get(['id', 'project_name']),
            'types' => collect(Equipment::TYPES)->mapWithKeys(fn ($t) => [$t => Equipment::typeLabel($t)])->all(),
            'statuses' => collect(Equipment::STATUSES)->mapWithKeys(fn ($s) => [$s => Equipment::statusLabel($s)])->all(),
        ])->layout('components.layouts.app');
    }
}
