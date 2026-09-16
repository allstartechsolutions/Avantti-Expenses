<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One stay of a piece of equipment at a project, a job site or with a person. The open stay has no `ended_at`. */
class EquipmentAssignment extends Model
{
    protected $fillable = [
        'equipment_id', 'project_id', 'job_site_id', 'responsible_user_id',
        'started_at', 'ended_at', 'notes', 'assigned_by', 'ended_by',
    ];

    protected $casts = [
        'started_at' => 'date',
        'ended_at' => 'date',
    ];

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function jobSite(): BelongsTo
    {
        return $this->belongsTo(JobSite::class);
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function endedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ended_by');
    }

    /**
     * A project or job site being deleted ends every open stay there — the
     * foreign keys null the pointers, but the log should say when it ended.
     */
    public static function closeFor(Project|JobSite $scope): void
    {
        $column = $scope instanceof JobSite ? 'job_site_id' : 'project_id';

        Equipment::where($column, $scope->id)->get()->each(fn (Equipment $equipment) => $equipment->endAssignment());
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('ended_at');
    }

    public function isOpen(): bool
    {
        return $this->ended_at === null;
    }

    /** "Project / Site", "Project", or the person, or "Unassigned". */
    public function locationLabel(): string
    {
        $parts = array_filter([
            $this->project?->project_name,
            $this->jobSite?->job_site_name,
        ]);

        if ($parts === []) {
            return $this->responsible?->name ?? __('Unassigned');
        }

        return implode(' / ', $parts);
    }
}
