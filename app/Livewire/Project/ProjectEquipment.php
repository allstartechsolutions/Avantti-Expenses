<?php

namespace App\Livewire\Project;

use App\Livewire\Concerns\ListsScopedEquipment;
use App\Models\JobSite;
use App\Models\Project;
use Livewire\Component;

/** The equipment on a project — its own and every one of its job sites'. */
class ProjectEquipment extends Component
{
    use ListsScopedEquipment;

    public Project $project;

    public function mount(Project $project): void
    {
        $this->project = $project;
        $this->guardScopedEquipment();
    }

    protected function equipmentScope(): Project|JobSite
    {
        return $this->project;
    }

    public function render()
    {
        return view('livewire.project.project-equipment', $this->scopedEquipmentViewData())
            ->layout('components.layouts.app');
    }
}
