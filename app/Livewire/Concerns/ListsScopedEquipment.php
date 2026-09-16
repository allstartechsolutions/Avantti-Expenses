<?php

namespace App\Livewire\Concerns;

use App\Models\Equipment;
use App\Models\EquipmentAssignment;
use App\Models\JobSite;
use App\Models\Project;
use Illuminate\Support\Collection;

/**
 * The equipment on one project or job site: what is here now, what has been
 * here, and a way to send something here. Shared by ProjectEquipment and
 * JobSiteEquipment (docs/project-jobsite-parity-rule.md) with one partial.
 *
 * Equipment is a company record, so the list is gated on `equipment.view`
 * (the role) and the screen on `project.view` for the scope — a confined
 * member of another project never reaches it.
 */
trait ListsScopedEquipment
{
    use AuthorizesAbility;

    public string $assignEquipmentId = '';

    public string $assignStartedAt = '';

    abstract protected function equipmentScope(): Project|JobSite;

    protected function guardScopedEquipment(): void
    {
        $this->authorizeAbility('project.view', $this->equipmentScope());
        $this->authorizeAbility('equipment.view');

        $this->assignStartedAt = now()->format('Y-m-d');
    }

    /** What is here now. */
    protected function equipmentHere(): Collection
    {
        return Equipment::query()
            ->assignedTo($this->equipmentScope())
            ->with(['project:id,project_name', 'jobSite:id,job_site_name', 'responsible:id,name'])
            ->orderBy('name')
            ->get();
    }

    /** What was here before. */
    protected function pastAssignments(): Collection
    {
        $scope = $this->equipmentScope();
        $column = $scope instanceof JobSite ? 'job_site_id' : 'project_id';

        return EquipmentAssignment::query()
            ->where($column, $scope->id)
            ->whereNotNull('ended_at')
            ->with(['equipment:id,name,asset_tag', 'jobSite:id,job_site_name', 'responsible:id,name', 'assignedBy:id,name'])
            ->orderByDesc('ended_at')
            ->limit(50)
            ->get();
    }

    /** The rest of the fleet, offered by the "send here" picker. */
    protected function availableEquipment(): Collection
    {
        $scope = $this->equipmentScope();

        return Equipment::query()
            ->inService()
            ->when($scope instanceof JobSite,
                fn ($q) => $q->where(fn ($w) => $w->whereNull('job_site_id')->orWhere('job_site_id', '!=', $scope->id)),
                fn ($q) => $q->where(fn ($w) => $w->whereNull('project_id')->orWhere('project_id', '!=', $scope->id)))
            ->orderBy('name')
            ->get(['id', 'name', 'asset_tag', 'project_id', 'job_site_id']);
    }

    public function assignHere(): void
    {
        $this->authorizeAbility('equipment.assign');

        $this->validate([
            'assignEquipmentId' => ['required', 'exists:equipment,id'],
            'assignStartedAt' => ['required', 'date'],
        ]);

        $equipment = Equipment::inService()->findOrFail((int) $this->assignEquipmentId);
        $scope = $this->equipmentScope();

        $equipment->assignTo(
            $scope instanceof JobSite ? $scope->project_id : $scope->id,
            $scope instanceof JobSite ? $scope->id : null,
            $equipment->responsible_user_id,
            $this->assignStartedAt,
        );

        $this->assignEquipmentId = '';
        session()->flash('message', __(':name is here now.', ['name' => $equipment->name]));
    }

    public function sendBack(int $equipmentId): void
    {
        $this->authorizeAbility('equipment.assign');

        $equipment = Equipment::assignedTo($this->equipmentScope())->findOrFail($equipmentId);
        $equipment->endAssignment();

        session()->flash('message', __(':name has left.', ['name' => $equipment->name]));
    }

    protected function scopedEquipmentViewData(): array
    {
        return [
            'here' => $this->equipmentHere(),
            'past' => $this->pastAssignments(),
            'available' => $this->availableEquipment(),
        ];
    }
}
