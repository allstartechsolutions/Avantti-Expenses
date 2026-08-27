<?php

namespace App\Livewire\Project;

use App\Livewire\Concerns\AuthorizesAbility;
use App\Livewire\Concerns\ManagesChangeOrders;
use App\Models\ChangeOrder;
use App\Models\Project;
use Livewire\Component;
use Livewire\WithFileUploads;

class ProjectChangeOrders extends Component
{
    use WithFileUploads, AuthorizesAbility, ManagesChangeOrders;

    public Project $project;

    /** Project-wide screen, so the list can be narrowed to one location. */
    public $changeOrderLocationFilter = 'all';

    public function mount(Project $project): void
    {
        $this->authorizeAbility('change-orders.view', $project);

        $this->project = $project;

        // "Create change order" on an RFI arrives here carrying ?fromRfi=.
        $this->applyChangeOrderQueryIntent();
    }

    protected function changeOrderProjectId(): int
    {
        return $this->project->id;
    }

    protected function afterChangeOrderSaved(): void
    {
        $this->project->refresh();
    }

    public function render()
    {
        $jobSites = $this->project->jobSites()->orderBy('job_site_name')->get();

        $query = $this->changeOrderQuery();

        if ($this->changeOrderLocationFilter === 'project') {
            $query->whereNull('job_site_id');
        } elseif ($this->changeOrderLocationFilter !== 'all' && is_numeric($this->changeOrderLocationFilter)) {
            $query->where('job_site_id', $this->changeOrderLocationFilter);
        }

        $changeOrders = $query->orderByDesc('requested_date')->orderByDesc('id')->get();

        return view('livewire.project.project-change-orders', [
            'changeOrders' => $changeOrders,
            'jobSites' => $jobSites,
            'summary' => $this->changeOrderSummary($changeOrders),
            'coBudget' => $this->changeOrderBudget(),
            'coLineSuggestions' => $this->changeOrderLineSearchResults(),
            'changeOrderRecord' => $this->editingChangeOrder
                ? ChangeOrder::with(['createdBy', 'approvedBy'])->find($this->editingChangeOrder)
                : null,
        ])->layout('components.layouts.app');
    }
}
