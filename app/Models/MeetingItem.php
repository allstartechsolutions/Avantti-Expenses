<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One line of the agenda, and the join between a meeting and a task: "at this
 * meeting, about this project, we discussed this task, and this is what was
 * said".
 *
 * The same task appears in June's minute and July's as two items with their
 * own discussion, which is why carrying an item forward never copies the work.
 */
class MeetingItem extends Model
{
    protected $fillable = [
        'meeting_id',
        'parent_id',
        'position',
        'project_id',
        'job_site_id',
        'type',
        'title',
        'discussion',
        'decision',
        'task_id',
        'carried_from_item_id',
        'status_at_meeting',
        'discussed',
        'created_by',
    ];

    protected $attributes = [
        'type' => 'action',
        'position' => 0,
        'discussed' => true,
    ];

    protected $casts = [
        'status_at_meeting' => 'array',
        'discussed' => 'boolean',
        'position' => 'integer',
    ];

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('position');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function jobSite(): BelongsTo
    {
        return $this->belongsTo(JobSite::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /** The item in the previous meeting that this one continues. */
    public function carriedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'carried_from_item_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    public function scopeActions(Builder $query): Builder
    {
        return $query->where('type', 'action');
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    public function isAction(): bool
    {
        return $this->type === 'action';
    }

    public function isCarriedForward(): bool
    {
        return $this->carried_from_item_id !== null;
    }

    public function isProjectLevel(): bool
    {
        return $this->project_id !== null && $this->job_site_id === null;
    }

    public function isGeneral(): bool
    {
        return $this->project_id === null && $this->job_site_id === null;
    }

    /**
     * Close the loop on a loaded agenda: every sub-item is handed the parent it
     * already came from, and told it has no sub-items of its own.
     *
     * Both ends were costing a query a row. `number()` walks upwards, so a
     * sub-item whose `parent` is not set goes and fetches the row the caller is
     * already holding; and the screens ask a sub-item whether it has children,
     * which the agenda never lets it have — `assertOwnParent()` refuses any
     * parent that is not itself a root, so an agenda is exactly two levels deep.
     *
     * @param  \Illuminate\Support\Collection<int, self>  $roots
     * @return \Illuminate\Support\Collection<int, self>
     */
    public static function linkParents(\Illuminate\Support\Collection $roots): \Illuminate\Support\Collection
    {
        foreach ($roots as $root) {
            if (! $root->relationLoaded('children')) {
                continue;
            }

            foreach ($root->children as $child) {
                $child->setRelation('parent', $root);
                $child->setRelation('children', new \Illuminate\Database\Eloquent\Collection);
            }
        }

        return $roots;
    }

    /**
     * The displayed number — 1, 2, 2.1 — computed from the position chain
     * rather than stored, so reordering the agenda does not rewrite rows.
     */
    public function number(): string
    {
        $ownIndex = $this->position + 1;

        return $this->parent_id !== null && $this->parent
            ? $this->parent->number().'.'.$ownIndex
            : (string) $ownIndex;
    }

    /**
     * How the task stood when the minute was published. The record has to
     * keep saying 60% after the task has moved on to 90%.
     */
    public function snapshotTask(): array
    {
        if ($this->task === null) {
            return [];
        }

        return [
            'status' => $this->task->status,
            'progress' => $this->task->progress,
            'due_date' => $this->task->due_date?->toDateString(),
            'owner_id' => $this->task->owner_id,
            'owner_name' => $this->task->owner?->name,
        ];
    }

    public function getTypeLabel(): string
    {
        return match ($this->type) {
            'information' => __('Information'),
            'decision' => __('Decision'),
            'action' => __('Action Item'),
            default => ucfirst($this->type),
        };
    }

    public function getTypeColor(): string
    {
        return match ($this->type) {
            'information' => 'gray',
            'decision' => 'purple',
            'action' => 'blue',
            default => 'gray',
        };
    }

    public function getScopeLabel(): string
    {
        if ($this->isGeneral()) {
            return __('General');
        }

        return $this->job_site_id !== null
            ? $this->project?->project_name.' › '.$this->jobSite?->job_site_name
            : (string) $this->project?->project_name;
    }
}
