<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CostCodeTemplate extends Model
{
    protected $fillable = [
        'name',
        'description',
        'is_default',
        'created_by',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function costCodes(): HasMany
    {
        return $this->hasMany(CostCode::class, 'template_id');
    }

    public function parentCostCodes(): HasMany
    {
        return $this->hasMany(CostCode::class, 'template_id')->whereNull('parent_id')->orderBy('sort_order');
    }

    /**
     * Duplicate this template with all its cost codes.
     */
    public function duplicate(int $userId, ?string $newName = null): CostCodeTemplate
    {
        $newTemplate = $this->replicate();
        $newTemplate->name = $newName ?? $this->name . ' (Copy)';
        $newTemplate->is_default = false;
        $newTemplate->created_by = $userId;
        $newTemplate->save();

        // Map old parent IDs to new parent IDs
        $parentMap = [];

        // First, copy all parent cost codes (no parent_id)
        foreach ($this->parentCostCodes as $parentCode) {
            $newParent = $parentCode->replicate();
            $newParent->template_id = $newTemplate->id;
            $newParent->save();
            $parentMap[$parentCode->id] = $newParent->id;
        }

        // Then, copy all child cost codes
        $childCodes = $this->costCodes()->whereNotNull('parent_id')->get();
        foreach ($childCodes as $childCode) {
            $newChild = $childCode->replicate();
            $newChild->template_id = $newTemplate->id;
            $newChild->parent_id = $parentMap[$childCode->parent_id] ?? null;
            $newChild->save();
        }

        return $newTemplate;
    }

    /**
     * Ensure only one template is marked as default.
     */
    public static function setDefault(int $templateId): void
    {
        static::where('is_default', true)->update(['is_default' => false]);
        static::where('id', $templateId)->update(['is_default' => true]);
    }
}
