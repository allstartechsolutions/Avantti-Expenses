<?php

namespace App\Livewire\Concerns;

use App\Models\Budget;
use App\Models\BudgetItem;
use Illuminate\Support\Collection;

/**
 * Budget resolution for components mounted with a Contract: the budget
 * for the contract's project + job-site pair, and the cost-code options
 * ordered parent-first for dropdowns.
 */
trait ResolvesContractBudget
{
    protected function locationBudget(): ?Budget
    {
        return Budget::where('project_id', $this->contract->project_id)
            ->where('job_site_id', $this->contract->job_site_id)
            ->first();
    }

    protected function budgetItemOptions(): Collection
    {
        $budget = $this->locationBudget();

        return $budget
            ? BudgetItem::where('budget_id', $budget->id)
                ->with('parent')
                ->orderBy('sort_order')
                ->get()
                ->sortBy(fn ($item) => [$item->parent?->sort_order ?? $item->sort_order, $item->parent_id ? 1 : 0, $item->sort_order])
                ->values()
            : collect();
    }
}
