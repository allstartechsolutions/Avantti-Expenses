<?php

namespace App\Console\Commands;

use App\Models\Expense;
use App\Models\ExpenseItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateExpenseItems extends Command
{
    protected $signature = 'expenses:migrate-items {--dry-run : Show what would be migrated without making changes}';

    protected $description = 'Migrate existing expenses to the new multi-item structure';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('DRY RUN - No changes will be made');
        }

        // Get expenses that don't have items yet
        $expenses = Expense::whereDoesntHave('items')
            ->whereNotNull('item_name')
            ->get();

        if ($expenses->isEmpty()) {
            $this->info('No expenses to migrate. All expenses already have items or no legacy data exists.');
            return Command::SUCCESS;
        }

        $this->info("Found {$expenses->count()} expenses to migrate.");

        $migrated = 0;
        $errors = 0;

        $progressBar = $this->output->createProgressBar($expenses->count());
        $progressBar->start();

        foreach ($expenses as $expense) {
            try {
                if (!$dryRun) {
                    DB::transaction(function () use ($expense) {
                        // Determine the unit to use
                        $unit = match ($expense->unit_type_used) {
                            'purchase' => $expense->purchase_unit,
                            'usage' => $expense->usage_unit,
                            default => $expense->usage_unit ?? $expense->purchase_unit ?? 'unit',
                        };

                        // Map old item_type to new enum values
                        // Old values: product, service, etc.
                        // New values: catalog, custom
                        $itemType = $expense->catalog_item_id ? 'catalog' : 'custom';

                        // Create the expense item from existing data
                        ExpenseItem::create([
                            'expense_id' => $expense->id,
                            'budget_item_id' => null, // Will be assigned to Miscellaneous on next edit
                            'catalog_item_id' => $expense->catalog_item_id,
                            'item_name' => $expense->item_name,
                            'item_type' => $itemType,
                            'description' => null,
                            'quantity' => $expense->quantity ?? 1,
                            'unit' => $unit,
                            'unit_price' => $expense->getRawOriginal('unit_price') / 100, // Convert from raw cents
                            'total_amount' => $expense->getRawOriginal('total_amount') / 100, // Convert from raw cents
                            'sort_order' => 0,
                        ]);
                    });
                }

                $migrated++;
            } catch (\Exception $e) {
                $errors++;
                $this->newLine();
                $this->error("Error migrating expense #{$expense->id}: {$e->getMessage()}");
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        if ($dryRun) {
            $this->info("Would migrate {$migrated} expenses.");
        } else {
            $this->info("Successfully migrated {$migrated} expenses.");
        }

        if ($errors > 0) {
            $this->warn("Errors encountered: {$errors}");
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
