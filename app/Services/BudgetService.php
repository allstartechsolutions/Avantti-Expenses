<?php

namespace App\Services;

use App\Models\Budget;
use App\Models\BudgetItem;
use App\Models\ExpenseItem;

class BudgetService
{
    /**
     * Get or create a budget for a location (project + optional job_site).
     * If no budget exists, creates one with only the default (catch-all) cost code.
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

            // Seed the catch-all bucket so uncoded costs have somewhere to land
            $budget->ensureDefaultItem();
        }

        return $budget;
    }

    /**
     * Get the catch-all cost code for a location — the bucket an uncoded cost
     * lands in. Creates the budget and the bucket if they don't exist.
     *
     * The bucket is the item flagged `is_default`, never a code looked up by
     * number, so contracts, expenses, purchase orders and change orders all
     * fall into the same one.
     */
    public static function getDefaultItem(int $projectId, ?int $jobSiteId, int $createdBy): BudgetItem
    {
        $budget = self::getOrCreateBudget($projectId, $jobSiteId, $createdBy);

        return $budget->ensureDefaultItem();
    }

    /**
     * Ensure an expense item has a budget_item_id.
     * If null, assigns it to the budget's default cost code.
     */
    public static function ensureBudgetItem(ExpenseItem $expenseItem): void
    {
        if ($expenseItem->budget_item_id) {
            return;
        }

        $expense = $expenseItem->expense;
        $defaultItem = self::getDefaultItem(
            $expense->project_id,
            $expense->job_site_id,
            $expense->created_by
        );

        $expenseItem->budget_item_id = $defaultItem->id;
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
