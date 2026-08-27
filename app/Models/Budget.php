<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Budget extends Model
{
    /**
     * Code and name seeded for a budget's catch-all bucket the first time one
     * is needed. Nothing ever *looks the bucket up* by this code — the
     * `is_default` flag is the only way it is resolved — so the user is free to
     * rename it or move the flag to a code of their own.
     */
    public const DEFAULT_ITEM_CODE = '999999';
    public const DEFAULT_ITEM_NAME = 'Miscellaneous';

    protected $fillable = [
        'project_id',
        'job_site_id',
        'name',
        'notes',
        'source_template_id',
        'created_by',
    ];

    protected $casts = [
        'locked_at' => 'datetime',
    ];

    // Deliberately not fillable: locking goes through lock()/unlock() so that
    // it can never happen without a history line beside it.

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

    // =========================================================================
    // LOCKING
    //
    // A locked budget's PLAN is fixed: no adding, editing or deleting cost
    // codes, no changing planned amounts, and the budget itself cannot be
    // deleted. Everything that reports against it carries on untouched —
    // expenses, purchase orders and change orders still code to it and the
    // variance keeps updating. Freezing the plan is not closing the job.
    //
    // Who may do it is `budget.lock`, grantable on a role, a template, a
    // project or job-site membership, or one person on one project.
    // =========================================================================

    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by');
    }

    public function lockHistories(): HasMany
    {
        return $this->hasMany(BudgetLockHistory::class)->latest('created_at');
    }

    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }

    /** Freeze the plan. Locking an already-locked budget is a no-op. */
    public function lock(?User $user = null, ?string $reason = null): void
    {
        if ($this->isLocked()) {
            return;
        }

        $this->forceFill([
            'locked_at' => now(),
            'locked_by' => $user?->id,
        ])->save();

        $this->recordLockChange('locked', $user, $reason);
    }

    /** Reopen the plan. Unlocking an unlocked budget is a no-op. */
    public function unlock(?User $user = null, ?string $reason = null): void
    {
        if (! $this->isLocked()) {
            return;
        }

        $this->forceFill([
            'locked_at' => null,
            'locked_by' => null,
        ])->save();

        $this->recordLockChange('unlocked', $user, $reason);
    }

    protected function recordLockChange(string $action, ?User $user, ?string $reason): void
    {
        $this->lockHistories()->create([
            'action' => $action,
            'user_id' => $user?->id,
            'reason' => $reason ?: null,
        ]);
    }

    /**
     * Get the item that uncoded (unallocated) amounts roll into.
     */
    public function defaultItem(): ?BudgetItem
    {
        return $this->items()->where('is_default', true)->first();
    }

    /**
     * Get the default item, creating it if this budget has none yet.
     *
     * A budget written before the flag existed may carry the old hardcoded
     * '999999 Miscellaneous' code; that item is adopted as the default rather
     * than a second catch-all bucket being created next to it.
     */
    public function ensureDefaultItem(): BudgetItem
    {
        if ($item = $this->defaultItem()) {
            return $item;
        }

        $legacy = $this->items()
            ->where('code', self::DEFAULT_ITEM_CODE)
            ->orderBy('id')
            ->first();

        if ($legacy) {
            $legacy->is_default = true;
            $legacy->save();

            return $legacy;
        }

        return $this->items()->create([
            'code' => self::DEFAULT_ITEM_CODE,
            'name' => self::DEFAULT_ITEM_NAME,
            'description' => __('Costs that have not been given a cost code.'),
            'budgeted_amount' => 0,
            'sort_order' => 99999,
            'is_default' => true,
        ]);
    }

    /**
     * Move the catch-all flag to another cost code. Exactly one item per budget
     * carries it.
     */
    public function setDefaultItem(BudgetItem $item): void
    {
        if ($item->budget_id !== $this->id) {
            throw new \InvalidArgumentException('The default cost code must belong to this budget.');
        }

        DB::transaction(function () use ($item) {
            $this->items()->where('is_default', true)->update(['is_default' => false]);
            $item->is_default = true;
            $item->save();
        });
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
     *
     * `requires_approval` and `default_approval_type` are copied across with
     * the rest. They have to be: a budget item carries no `cost_code_id` back
     * to the library row it came from, so a flag left behind on `cost_codes`
     * could never be read from the budget line that needs it
     * (docs/rfi-aprovacoes-discovery.md item 4).
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
                'requires_approval' => $parentCode->requires_approval,
                'default_approval_type' => $parentCode->default_approval_type,
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
                'requires_approval' => $childCode->requires_approval,
                'default_approval_type' => $childCode->default_approval_type,
            ]);
        }
    }
}
