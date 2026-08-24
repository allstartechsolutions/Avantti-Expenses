<?php

namespace App\Models;

use App\Enums\AccessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class Role extends Model
{
    protected $fillable = [
        'name',
        'description',
        'access_scope',
        'approval_limit',
        'seeded_areas',
    ];

    protected $casts = [
        'access_scope' => AccessScope::class,
        // The most anybody with this role may approve away from a project, in
        // cents. Null — every seeded role — means no ceiling, which is exactly
        // what the application enforced before F0. One person can override it
        // on themselves; see User::effectiveApprovalLimit().
        'approval_limit' => 'integer',
        'seeded_areas' => 'array',
    ];

    /**
     * Get all users with this role
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * The company-wide abilities this role grants. The admin role holds none
     * and needs none — it is allowed everything before this table is read.
     */
    public function abilityRows(): HasMany
    {
        return $this->hasMany(RoleAbility::class);
    }

    /** @return array<int, string> */
    public function abilities(): array
    {
        return $this->abilityRows->pluck('ability')->all();
    }

    /**
     * The roles the application ships with. Their names are compared in code
     * that has not had its permission pass yet, so they cannot be renamed or
     * deleted; their abilities can be edited freely.
     */
    public const SYSTEM = ['admin', 'manager', 'employee'];

    /**
     * Human label for the role.
     *
     * Role names are stored lower-case and are user-creatable, so a custom
     * role has no key to translate — __() returns the name unchanged, which is
     * the right answer for one somebody typed themselves. The three seeded
     * roles do have keys. This mirrors access-index.blade.php, which already
     * wrapped $role->name in __().
     */
    public function getLabel(): string
    {
        return __(ucfirst($this->name));
    }

    public function isAdmin(): bool
    {
        return $this->name === 'admin';
    }

    public function isSystem(): bool
    {
        return in_array($this->name, self::SYSTEM, true);
    }

    /**
     * Whether people holding this role see every project, or only the ones
     * they have been added to. A user can override it; most never do.
     */
    public function confinesToAssignments(): bool
    {
        return $this->access_scope === AccessScope::ASSIGNED;
    }

    /**
     * Areas this role has already been offered by the seeder.
     *
     * Kept explicitly so that `permissions:sync` can tell a genuinely new area
     * from one whose abilities somebody deliberately revoked — asking whether
     * the role holds anything from an area cannot tell those apart, and
     * guessing means handing back a permission an administrator took away.
     *
     * @return array<int, string>
     */
    public function seededAreas(): array
    {
        return $this->seeded_areas ?? [];
    }

    public function markAreasSeeded(array $areas): void
    {
        // The seeder runs from a migration that predates this column on a
        // database being built from scratch. The migration that adds the
        // column backfills every role with the whole catalogue, so there is
        // nothing to lose by skipping here.
        if (! Schema::hasColumn($this->getTable(), 'seeded_areas')) {
            return;
        }

        $this->update([
            'seeded_areas' => array_values(array_unique(array_merge($this->seededAreas(), $areas))),
        ]);
    }

    /**
     * Replace the role's ability list with the given one.
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
