<?php

namespace App\Livewire\Budget;

use App\Models\Budget;
use Livewire\Component;

class BudgetCostGrid extends Component
{
    public Budget $budget;

    public function mount(Budget $budget)
    {
        $this->budget = $budget->load(['project', 'jobSite']);
    }

    public function render()
    {
        return view('livewire.budget.budget-cost-grid', [
            'grid' => $this->budget->costCodeGrid(),
        ])->layout('components.layouts.app');
    }
}
