<?php

namespace App\Livewire\Concerns;

use App\Models\Budget;
use App\Models\BudgetItem;
use App\Models\Contract;
use Illuminate\Support\Collection;

/**
 * Cost-code allocation editor shared by ContractCreate and ContractEdit.
 * Allocation is optional: amounts not allocated to a code roll into the
 * budget's default item (or "Unassigned") at display time.
 */
trait ManagesContractAllocations
{
    /** @var array<int, array{budget_item_id: int, code_display: string, amount: mixed}> */
    public array $allocations = [];

    public $allocationSearch = '';

    abstract protected function allocationProjectId(): int;

    protected function allocationBudget(): ?Budget
    {
        return Budget::where('project_id', $this->allocationProjectId())
            ->where('job_site_id', $this->job_site_id ?: null)
            ->first();
    }

    public function addAllocation($budgetItemId): void
    {
        $budget = $this->allocationBudget();
        $item = $budget ? BudgetItem::where('budget_id', $budget->id)->find($budgetItemId) : null;

        if (! $item) {
            return;
        }

        $this->allocationSearch = '';

        foreach ($this->allocations as $row) {
            if ((int) $row['budget_item_id'] === $item->id) {
                return;
            }
        }

        $this->allocations[] = [
            'budget_item_id' => $item->id,
            'code_display' => $item->code . ' - ' . $item->name,
            'amount' => '',
        ];
    }

    public function removeAllocation(int $index): void
    {
        unset($this->allocations[$index]);
        $this->allocations = array_values($this->allocations);
    }

    public function updatedJobSiteId(): void
    {
        // Codes belong to the previous location's budget.
        $this->allocations = [];
        $this->allocationSearch = '';
    }

    public function allocatedTotal(): float
    {
        return round(collect($this->allocations)->sum(fn ($row) => (float) ($row['amount'] ?: 0)), 2);
    }

    public function allocationRemainder(): float
    {
        return round((float) ($this->amount ?: 0) - $this->allocatedTotal(), 2);
    }

    /**
     * Validate allocation rows. Call after the main $this->validate();
     * returns false (with errors set) when saving must stop.
     */
    protected function allocationsValid(): bool
    {
        if (empty($this->allocations)) {
            return true;
        }

        $this->validate(
            ['allocations.*.amount' => 'required|numeric|min:0.01'],
            [],
            ['allocations.*.amount' => 'allocation amount']
        );

        if ($this->allocationRemainder() < -0.009) {
            $this->addError('allocations', __('The allocated total cannot exceed the contract amount.'));

            return false;
        }

        $budget = $this->allocationBudget();
        $validIds = $budget
            ? BudgetItem::where('budget_id', $budget->id)->pluck('id')->all()
            : [];

        foreach ($this->allocations as $row) {
            if (! in_array((int) $row['budget_item_id'], $validIds, true)) {
                $this->addError('allocations', __('One or more cost codes do not belong to this location\'s budget. Remove them and pick codes again.'));

                return false;
            }
        }

        return true;
    }

    /**
     * Persist the rows for the contract. Run inside a transaction.
     */
    protected function syncAllocations(Contract $contract): void
    {
        $contract->allocations()->delete();

        foreach ($this->allocations as $row) {
            $contract->allocations()->create([
                'budget_item_id' => $row['budget_item_id'],
                'amount' => $row['amount'],
            ]);
        }
    }

    protected function allocationSearchResults(): Collection
    {
        if (! $this->allocationSearch || strlen($this->allocationSearch) < 1) {
            return collect();
        }

        $budget = $this->allocationBudget();
        if (! $budget) {
            return collect();
        }

        $selectedIds = array_map(fn ($row) => (int) $row['budget_item_id'], $this->allocations);

        return BudgetItem::where('budget_id', $budget->id)
            ->whereNotIn('id', $selectedIds)
            ->where(function ($q) {
                $q->where('code', 'like', '%' . $this->allocationSearch . '%')
                    ->orWhere('name', 'like', '%' . $this->allocationSearch . '%');
            })
            ->orderBy('sort_order')
            ->take(15)
            ->get();
    }
}
