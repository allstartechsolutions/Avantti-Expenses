<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Budget extends Model
{
    protected $fillable = [
        'project_id',
        'job_site_id',
        'name',
        'notes',
        'source_template_id',
        'created_by',
    ];

    /**
     * Get the project that owns this budget.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the job site that owns this budget (nullable).
     */
    public function jobSite(): BelongsTo
    {
        return $this->belongsTo(JobSite::class);
    }

    /**
     * Get the template this budget was created from (nullable).
     */
    public function sourceTemplate(): BelongsTo
    {
        return $this->belongsTo(CostCodeTemplate::class, 'source_template_id');
    }

    /**
     * Get the user who created this budget.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get all budget items.
     */
    public function items(): HasMany
    {
        return $this->hasMany(BudgetItem::class);
    }

    /**
     * Excel-style cost grid for this budget's location: every cost code
     * grouped section → lines, with contract commitments and payments
     * aggregated per code (via each contract's costCodeSchedule(), so
     * default-code fallback applies). Cancelled contracts are excluded.
     *
     * Returns ['sections' => [...], 'unassigned' => ?row, 'totals' => row].
     * Section: ['item' => parent BudgetItem, 'rows' => [row...], 'subtotal' => row, 'pct_of_budget' => ?float]
     * Row: budget_item_id, code, name, budgeted, contracted, paid, percent (weighted, nullable), balance.
     */
    public function costCodeGrid(): array
    {
        $contracts = Contract::where('project_id', $this->project_id)
            ->where('job_site_id', $this->job_site_id)
            ->committed()
            ->get();

        $agg = [];
        foreach ($contracts as $contract) {
            foreach ($contract->costCodeSchedule() as $row) {
                $key = $row['budget_item_id'] ?? 0;
                $agg[$key] ??= ['contracted' => 0.0, 'paid' => 0.0, 'wsum' => 0.0, 'wtot' => 0.0];
                $agg[$key]['contracted'] += $row['scheduled'];
                $agg[$key]['paid'] += $row['paid'];
                if ($row['percent_complete'] !== null && $row['scheduled'] > 0) {
                    $agg[$key]['wsum'] += $row['percent_complete'] * $row['scheduled'];
                    $agg[$key]['wtot'] += $row['scheduled'];
                }
            }
        }

        $makeRow = function (?BudgetItem $item) use ($agg): array {
            $a = $agg[$item?->id ?? 0] ?? ['contracted' => 0.0, 'paid' => 0.0, 'wsum' => 0.0, 'wtot' => 0.0];

            return [
                'budget_item_id' => $item?->id,
                'code' => $item?->code ?? '',
                'name' => $item?->name ?? __('Unassigned'),
                'is_default' => (bool) ($item?->is_default ?? false),
                'budgeted' => $item?->budgeted_amount ?? 0.0,
                'contracted' => round($a['contracted'], 2),
                'paid' => round($a['paid'], 2),
                'percent' => $a['wtot'] > 0 ? round($a['wsum'] / $a['wtot'], 2) : null,
                'balance' => round($a['contracted'] - $a['paid'], 2),
            ];
        };

        $sumRows = function (array $rows): array {
            $wsum = 0.0;
            $wtot = 0.0;
            foreach ($rows as $r) {
                if ($r['percent'] !== null && $r['contracted'] > 0) {
                    $wsum += $r['percent'] * $r['contracted'];
                    $wtot += $r['contracted'];
                }
            }

            return [
                'budgeted' => round(array_sum(array_column($rows, 'budgeted')), 2),
                'contracted' => round(array_sum(array_column($rows, 'contracted')), 2),
                'paid' => round(array_sum(array_column($rows, 'paid')), 2),
                'percent' => $wtot > 0 ? round($wsum / $wtot, 2) : null,
                'balance' => round(array_sum(array_column($rows, 'balance')), 2),
            ];
        };

        $sections = [];
        $allRows = [];
        foreach ($this->parentItems()->with(['children'])->get() as $parent) {
            $rows = [$makeRow($parent)];
            foreach ($parent->children as $child) {
                $rows[] = $makeRow($child);
            }
            $subtotal = $sumRows($rows);
            $sections[] = ['item' => $parent, 'rows' => $rows, 'subtotal' => $subtotal];
            $allRows = array_merge($allRows, $rows);
        }

        $unassigned = isset($agg[0]) ? $makeRow(null) : null;
        if ($unassigned) {
            $allRows[] = $unassigned;
        }

        $totals = $sumRows($allRows);

        foreach ($sections as &$section) {
            $section['pct_of_budget'] = $totals['budgeted'] > 0
                ? round($section['subtotal']['budgeted'] / $totals['budgeted'] * 100, 2)
                : null;
        }

        return ['sections' => $sections, 'unassigned' => $unassigned, 'totals' => $totals];
    }

    /**
     * Get the item that uncoded (unallocated) amounts roll into.
     */
    public function defaultItem(): ?BudgetItem
    {
        return $this->items()->where('is_default', true)->first();
    }

    /**
     * Get only parent (top-level) budget items.
     */
    public function parentItems(): HasMany
    {
        return $this->hasMany(BudgetItem::class)->whereNull('parent_id')->orderBy('sort_order');
    }

    /**
     * Check if this is a project-level budget (no job site).
     */
    public function isProjectLevel(): bool
    {
        return is_null($this->job_site_id);
    }

    /**
     * Get the location name for display.
     */
    public function getLocationNameAttribute(): string
    {
        if ($this->isProjectLevel()) {
            return 'Project (General)';
        }

        return $this->jobSite?->job_site_name ?? 'Unknown Job Site';
    }

    /**
     * Get the total amount (sum of all budget items) in dollars.
     */
    public function getTotalAmountAttribute(): float
    {
        // Sum is in cents, convert to dollars
        $totalCents = $this->items()->sum('budgeted_amount');

        return round($totalCents / 100, 2);
    }

    /**
     * Get the count of budget items.
     */
    public function getItemsCountAttribute(): int
    {
        return $this->items()->count();
    }

    /**
     * Apply a cost code template to this budget.
     * Copies all cost codes from the template with $0.00 amounts.
     */
    public function applyTemplate(CostCodeTemplate $template): void
    {
        // Update the source template reference
        $this->source_template_id = $template->id;
        $this->save();

        // Map old parent IDs to new parent IDs
        $parentMap = [];

        // First, copy all parent cost codes (no parent_id)
        foreach ($template->parentCostCodes as $parentCode) {
            $newItem = $this->items()->create([
                'code' => $parentCode->code,
                'name' => $parentCode->name,
                'description' => $parentCode->description,
                'budgeted_amount' => 0,
                'sort_order' => $parentCode->sort_order,
            ]);
            $parentMap[$parentCode->id] = $newItem->id;
        }

        // Then, copy all child cost codes
        $childCodes = $template->costCodes()->whereNotNull('parent_id')->orderBy('sort_order')->get();
        foreach ($childCodes as $childCode) {
            $this->items()->create([
                'parent_id' => $parentMap[$childCode->parent_id] ?? null,
                'code' => $childCode->code,
                'name' => $childCode->name,
                'description' => $childCode->description,
                'budgeted_amount' => 0,
                'sort_order' => $childCode->sort_order,
            ]);
        }
    }
}
