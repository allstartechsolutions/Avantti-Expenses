<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\BudgetItem;
use App\Models\ExpenseItem;

class BudgetService
{
    public const MISCELLANEOUS_CODE = '999999';
    public const MISCELLANEOUS_NAME = 'Miscellaneous';

    /**
     * Get or create a budget for a location (project + optional job_site).
     * If no budget exists, creates one with only the Miscellaneous item.
     */
    public static function getOrCreateBudget(int $projectId, ?int $jobSiteId, int $createdBy): Budget
    {
        $budget = Budget::where('project_id', $projectId)
            ->where('job_site_id', $jobSiteId)
            ->first();

        if (!$budget) {
            $budget = Budget::create([
                'project_id' => $projectId,
                'job_site_id' => $jobSiteId,
                'name' => $jobSiteId
                    ? 'Job Site Budget'
                    : 'Project Budget',
                'notes' => 'Auto-created for expense tracking',
                'created_by' => $createdBy,
            ]);

            // Create Miscellaneous item
            self::getOrCreateMiscellaneousItem($budget);
        }

        return $budget;
    }

    /**
     * Get or create the Miscellaneous budget item for a budget.
     */
    public static function getOrCreateMiscellaneousItem(Budget $budget): BudgetItem
    {
        $item = $budget->items()
            ->where('code', self::MISCELLANEOUS_CODE)
            ->first();

        if (!$item) {
            $item = $budget->items()->create([
                'code' => self::MISCELLANEOUS_CODE,
                'name' => self::MISCELLANEOUS_NAME,
                'description' => 'Uncategorized expenses',
                'budgeted_amount' => 0,
                'sort_order' => 99999,
            ]);
        }

        return $item;
    }

    /**
     * Get the Miscellaneous budget item for a location.
     * Creates budget and item if they don't exist.
     */
    public static function getMiscellaneousItem(int $projectId, ?int $jobSiteId, int $createdBy): BudgetItem
    {
        $budget = self::getOrCreateBudget($projectId, $jobSiteId, $createdBy);

        return self::getOrCreateMiscellaneousItem($budget);
    }

    /**
     * Ensure an expense item has a budget_item_id.
     * If null, assigns to Miscellaneous.
     */
    public static function ensureBudgetItem(ExpenseItem $expenseItem): void
    {
        if ($expenseItem->budget_item_id) {
            return;
        }

        $expense = $expenseItem->expense;
        $miscItem = self::getMiscellaneousItem(
            $expense->project_id,
            $expense->job_site_id,
            $expense->created_by
        );

        $expenseItem->budget_item_id = $miscItem->id;
        $expenseItem->save();
    }

    /**
     * Get all budget items for a location, suitable for dropdown selection.
     * Returns items with full code path for display.
     */
    public static function getBudgetItemsForDropdown(int $projectId, ?int $jobSiteId): array
    {
        $budget = Budget::where('project_id', $projectId)
            ->where('job_site_id', $jobSiteId)
            ->first();

        if (!$budget) {
            return [];
        }

        $items = [];

        // Get parent items with their children
        $parentItems = $budget->parentItems()->with('children')->get();

        foreach ($parentItems as $parent) {
            // Add parent item
            $items[] = [
                'id' => $parent->id,
                'code' => $parent->code,
                'name' => $parent->name,
                'full_name' => $parent->code . ' - ' . $parent->name,
                'is_parent' => true,
            ];

            // Add children indented
            foreach ($parent->children as $child) {
                $items[] = [
                    'id' => $child->id,
                    'code' => $child->code,
                    'name' => $child->name,
                    'full_name' => '    ' . $child->code . ' - ' . $child->name,
                    'is_parent' => false,
                ];
            }
        }

        return $items;
    }
}
