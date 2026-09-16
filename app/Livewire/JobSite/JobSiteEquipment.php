<?php

namespace App\Livewire\JobSite;

use App\Livewire\Concerns\ListsScopedEquipment;
use App\Models\JobSite;
use App\Models\Project;
use Livewire\Component;

/** The equipment on one job site — the project page with the location fixed. */
class JobSiteEquipment extends Component
{
    use ListsScopedEquipment;

    public JobSite $jobSite;

    public function mount(JobSite $jobSite): void
    {
        $this->jobSite = $jobSite;
        $this->guardScopedEquipment();
    }

    protected function equipmentScope(): Project|JobSite
    {
        return $this->jobSite;
    }

    public function render()
    {
        return view('livewire.job-site.job-site-equipment', $this->scopedEquipmentViewData())
            ->layout('components.layouts.app');
    }
}
