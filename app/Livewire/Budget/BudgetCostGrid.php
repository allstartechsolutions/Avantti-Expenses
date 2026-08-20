<?php

namespace App\Livewire\Budget;

use App\Models\Budget;
use App\Services\CostCodeLedger;
use Livewire\Component;

class BudgetCostGrid extends Component
{
    public Budget $budget;

    /** Hide cost codes with nothing budgeted and nothing spent. */
    public bool $hideEmpty = false;

    public function mount(Budget $budget)
    {
        $this->budget = $budget->load(['project', 'jobSite']);
    }

    public function render()
    {
        return view('livewire.budget.budget-cost-grid', [
            'grid' => CostCodeLedger::for($this->budget)->grid(),
        ])->layout('components.layouts.app');
    }
}
