<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

/**
 * A folder in the project / job site file repository.
 * See docs/file-repository-plan.md.
 */
class DocumentFolder extends Model
{
    use SoftDeletes;

    /**
     * How deep a user may nest folders. Deep trees are unnavigable on a phone
     * and make the breadcrumb useless.
     */
    public const MAX_DEPTH = 5;

    protected $fillable = [
        'project_id',
        'job_site_id',
        'parent_id',
        'name',
        'description',
        'created_by',
        'updated_by',
    ];

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function jobSite(): BelongsTo
    {
        return $this->belongsTo(JobSite::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('name');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'folder_id');
    }

    public function shares(): HasMany
    {
        return $this->hasMany(DocumentShare::class, 'folder_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    /**
     * Folders belonging to a project, optionally narrowed to one job site.
     * Passing null for the job site means project level only.
     */
    public function scopeForLocation(Builder $query, int $projectId, ?int $jobSiteId): Builder
    {
        return $query->where('project_id', $projectId)
            ->where(fn (Builder $q) => $jobSiteId
                ? $q->where('job_site_id', $jobSiteId)
                : $q->whereNull('job_site_id'));
    }

    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    public function isProjectLevel(): bool
    {
        return is_null($this->job_site_id);
    }

    /**
     * This folder's ancestors, outermost first — the breadcrumb.
     *
     * @return Collection<int, self>
     */
    public function ancestors(): Collection
    {
        $ancestors = collect();
        $folder = $this->parent;
        $guard = 0;

        while ($folder && $guard++ < self::MAX_DEPTH + 1) {
            $ancestors->prepend($folder);
            $folder = $folder->parent;
        }

        return $ancestors;
    }

    /**
     * 1 for a root folder.
     */
    public function depth(): int
    {
        return $this->ancestors()->count() + 1;
    }

    /**
     * Ids of this folder and everything beneath it — used when counting the
     * documents a folder holds, and when refusing to move a folder into its
     * own subtree.
     *
     * @return array<int, int>
     */
    public function descendantIds(): array
    {
        $ids = [$this->id];

        foreach ($this->children as $child) {
            $ids = array_merge($ids, $child->descendantIds());
        }

        return $ids;
    }
}
