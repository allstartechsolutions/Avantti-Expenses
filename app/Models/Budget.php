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
