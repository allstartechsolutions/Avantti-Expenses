<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A named, reusable ability list — "Site Supervisor", "Procurement",
 * "Client (read only)". Copied onto a membership when somebody is invited, and
 * from then on the membership is the truth: editing a template never changes
 * what an existing member can already do.
 */
class PermissionTemplate extends Model
{
    protected $fillable = [
        'key',
        'name',
        'description',
        'level',
        'is_guest',
        'is_system',
        'can_see_money',
        'approval_limit',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_guest' => 'boolean',
        'is_system' => 'boolean',
        'can_see_money' => 'boolean',
        'approval_limit' => 'integer',
    ];

    public function abilityRows(): HasMany
    {
        return $this->hasMany(PermissionTemplateAbility::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /** @return array<int, string> */
    public function abilities(): array
    {
        return $this->abilityRows->pluck('ability')->all();
    }

    public function scopeForLevel(Builder $query, string $level): Builder
    {
        return $query->where('level', $level);
    }

    /** Templates offered when adding staff — guest templates are kept separate. */
    public function scopeForStaff(Builder $query): Builder
    {
        return $query->where('is_guest', false);
    }

    public function scopeForGuests(Builder $query): Builder
    {
        return $query->where('is_guest', true);
    }

    /**
     * Replace the template's ability list with the given one.
     */
    public function syncAbilities(array $abilities): void
    {
        $abilities = array_values(array_unique($abilities));

        $this->abilityRows()->whereNotIn('ability', $abilities)->delete();

        $existing = $this->abilityRows()->pluck('ability')->all();

        foreach (array_diff($abilities, $existing) as $ability) {
            $this->abilityRows()->create(['ability' => $ability]);
        }

        $this->unsetRelation('abilityRows');
    }
}
