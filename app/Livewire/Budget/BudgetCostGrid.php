<?php

namespace App\Livewire\Budget;

use App\Livewire\Concerns\AuthorizesAbility;
use App\Models\Budget;
use App\Services\CostCodeLedger;
use Livewire\Component;

class BudgetCostGrid extends Component
{
    use AuthorizesAbility;

    public Budget $budget;

    /**
     * Cost codes with nothing budgeted, committed or spent are noise on a long
     * template, so they stay out of the way until the user asks for them. The
     * totals are unaffected either way.
     */
    public bool $showEmpty = false;

    public function mount(Budget $budget)
    {
        $this->authorizeAbility('budget.view', $budget);

        $this->budget = $budget->load(['project', 'jobSite']);
    }

    public function render()
    {
        $grid = CostCodeLedger::for($this->budget)->grid();

        return view('livewire.budget.budget-cost-grid', [
            'grid' => $this->showEmpty ? $grid : CostCodeLedger::withActivityOnly($grid),
        ])->layout('components.layouts.app');
    }
}
