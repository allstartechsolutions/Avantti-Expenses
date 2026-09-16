<?php

namespace App\Livewire\Equipment;

use App\Livewire\Concerns\AuthorizesAbility;
use App\Models\Equipment;
use App\Models\EquipmentMaintenance;
use App\Models\Project;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The fleet-wide maintenance list: what is overdue, due, due soon, planned
 * and under way across every piece of equipment, grouped by month. Each
 * row links to the equipment page, where the work is scheduled and done.
 */
class MaintenanceIndex extends Component
{
    use AuthorizesAbility;

    /** '' (open) | overdue | due_soon | in_progress | completed | cancelled */
    #[Url(except: '')]
    public string $view = '';

    #[Url(except: '')]
    public string $typeFilter = '';

    #[Url(except: '')]
    public string $equipmentFilter = '';

    #[Url(except: '')]
    public string $projectFilter = '';

    public function mount(): void
    {
        $this->authorizeAbility('equipment.view');
    }

    public function setView(string $view): void
    {
        $this->view = in_array($view, ['', 'overdue', 'due_soon', 'in_progress', 'completed', 'cancelled'], true) ? $view : '';
    }

    public function hasFilters(): bool
    {
        return $this->typeFilter !== '' || $this->equipmentFilter !== '' || $this->projectFilter !== '';
    }

    public function clearFilters(): void
    {
        $this->reset(['typeFilter', 'equipmentFilter', 'projectFilter']);
    }

    public function render()
    {
        $today = now();

        $base = EquipmentMaintenance::query()
            ->with(['equipment.project:id,project_name', 'equipment.jobSite:id,job_site_name', 'plan', 'supplier:id,name'])
            ->whereHas('equipment', fn ($q) => $q->inService())
            ->when($this->typeFilter !== '', fn ($q) => $q->where('maintenance_type', $this->typeFilter))
            ->when($this->equipmentFilter !== '', fn ($q) => $q->where('equipment_id', (int) $this->equipmentFilter))
            ->when($this->projectFilter !== '', fn ($q) => $q->whereHas('equipment', fn ($e) => $e->where('project_id', (int) $this->projectFilter)));

        $open = (clone $base)->open()->get();

        $counts = [
            'overdue' => $open->filter(fn ($m) => $m->urgency($today) === 'overdue' || $m->urgency($today) === 'due')->count(),
            'due_soon' => $open->filter(fn ($m) => $m->urgency($today) === 'due_soon')->count(),
            'scheduled' => $open->filter(fn ($m) => $m->urgency($today) === 'scheduled')->count(),
            'in_progress' => $open->filter(fn ($m) => $m->status === 'in_progress')->count(),
        ];

        $rows = match ($this->view) {
            'overdue' => $open->filter(fn ($m) => in_array($m->urgency($today), ['overdue', 'due'], true)),
            'due_soon' => $open->filter(fn ($m) => $m->urgency($today) === 'due_soon'),
            'in_progress' => $open->filter(fn ($m) => $m->status === 'in_progress'),
            'completed' => (clone $base)->where('status', 'completed')->orderByDesc('completed_date')->limit(200)->get(),
            'cancelled' => (clone $base)->where('status', 'cancelled')->orderByDesc('updated_at')->limit(200)->get(),
            default => $open,
        };

        // Open work sorted by the date it comes due; undated (meter-only) last.
        if (! in_array($this->view, ['completed', 'cancelled'], true)) {
            $rows = $rows->sortBy(fn ($m) => ($m->scheduled_date?->format('Y-m-d') ?? '9999-12-31').'-'.$m->id)->values();
        }

        $grouped = $rows->groupBy(function ($m) {
            $date = in_array($this->view, ['completed', 'cancelled'], true)
                ? ($m->completed_date ?? $m->updated_at)
                : $m->scheduled_date;

            return $date ? $date->format('Y-m') : 'none';
        });

        return view('livewire.equipment.maintenance-index', [
            'grouped' => $grouped,
            'counts' => $counts,
            'equipmentOptions' => Equipment::inService()->orderBy('name')->get(['id', 'name']),
            'projects' => Project::visibleTo(auth()->user())->orderBy('project_name')->get(['id', 'project_name']),
        ])->layout('components.layouts.app');
    }
}
