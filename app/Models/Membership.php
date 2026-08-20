<?php

namespace App\Models;

use App\Enums\MembershipStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A person on one project or one job site, with their own ability list.
 *
 * The rules the resolver applies to these (docs/permissions-module-plan.md §6):
 * a job-site membership overrides its project membership for that site, a
 * project membership cascades to every job site under it, and only an ACTIVE
 * membership grants anything at all.
 */
class Membership extends Model
{
    protected $fillable = [
        'user_id',
        'scopeable_type',
        'scopeable_id',
        'permission_template_id',
        'title',
        'can_see_money',
        'approval_limit',
        'status',
        'invited_by',
        'invited_at',
        'accepted_at',
        'revoked_at',
    ];

    protected $casts = [
        'can_see_money' => 'boolean',
        'approval_limit' => 'integer',
        'status' => MembershipStatus::class,
        'invited_at' => 'datetime',
        'accepted_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** The project or job site this membership is on. */
    public function scopeable(): MorphTo
    {
        return $this->morphTo();
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(PermissionTemplate::class, 'permission_template_id');
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function abilityRows(): HasMany
    {
        return $this->hasMany(MembershipAbility::class);
    }

    /** @return array<int, string> */
    public function abilities(): array
    {
        return $this->abilityRows->pluck('ability')->all();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', MembershipStatus::ACTIVE->value);
    }

    public function scopeOnProjects(Builder $query): Builder
    {
        return $query->where('scopeable_type', Project::class);
    }

    public function scopeOnJobSites(Builder $query): Builder
    {
        return $query->where('scopeable_type', JobSite::class);
    }

    public function isProjectLevel(): bool
    {
        return $this->scopeable_type === Project::class;
    }

    public function grantsAccess(): bool
    {
        return $this->status->grantsAccess();
    }

    /**
     * What the Team tab shows as the member's access: the template's name, or
     * "Custom (based on X)" once somebody has tweaked it.
     */
    public function accessLabel(): string
    {
        if (! $this->template) {
            return __('Custom');
        }

        $mine = $this->abilities();
        $theirs = $this->template->abilities();

        sort($mine);
        sort($theirs);

        return $mine === $theirs
            ? $this->template->name
            : __('Custom (based on :template)', ['template' => $this->template->name]);
    }

    /**
     * Replace the membership's ability list with the given one.
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
