<?php

namespace App\Models;

use App\Enums\DocumentCategory;
use App\Services\DocumentSettings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * A document in the project / job site repository. The bytes live in
 * DocumentVersion; this row is what the user names, moves, tags and shares,
 * and it survives every re-upload.
 *
 * See docs/file-repository-plan.md.
 */
class Document extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'project_id',
        'job_site_id',
        'folder_id',
        'name',
        'description',
        'category',
        'is_internal',
        'current_version_id',
        'current_size_bytes',
        'current_mime_type',
        'current_version_number',
        'uploaded_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'is_internal' => 'boolean',
        'category' => DocumentCategory::class,
        'current_size_bytes' => 'integer',
        'current_version_number' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (self $document) {
            $document->uuid ??= (string) Str::uuid();
        });
    }

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

    public function folder(): BelongsTo
    {
        return $this->belongsTo(DocumentFolder::class, 'folder_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class)->orderByDesc('version_number');
    }

    /**
     * Only the versions that finished uploading — what the history panel and
     * the version count should ever show.
     */
    public function availableVersions(): HasMany
    {
        return $this->versions()->where('upload_status', DocumentVersion::STATUS_AVAILABLE);
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class, 'current_version_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(DocumentTag::class)->orderBy('name');
    }

    public function shares(): HasMany
    {
        return $this->hasMany(DocumentShare::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(DocumentActivity::class)->latest();
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    /**
     * Documents of a project, optionally narrowed to one job site. Null for
     * the job site means project level only; omit the argument entirely (by
     * calling scopeForProject) to get everything under the project.
     */
    public function scopeForLocation(Builder $query, int $projectId, ?int $jobSiteId): Builder
    {
        return $query->where('project_id', $projectId)
            ->where(fn (Builder $q) => $jobSiteId
                ? $q->where('job_site_id', $jobSiteId)
                : $q->whereNull('job_site_id'));
    }

    /**
     * Hide internal documents from users who may not see them.
     */
    /**
     * Narrow a list to what this person may actually see.
     *
     * Two separate questions, and until M12 only the second was asked:
     *
     *   1. May they open documents on this project at all? A list is always
     *      built inside one location, so the caller has already been guarded
     *      by `documents.view` on that scope — this clause exists for the
     *      cross-project lists, which have none.
     *   2. May they see the ones flagged internal? That is
     *      `documents.see_internal`, which used to be "admin or manager".
     */
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->is_admin) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($user) {
            $q->where('is_internal', false)
                ->orWhere(fn (Builder $internal) => $internal
                    ->where('is_internal', true)
                    ->whereIn('id', static::idsWithInternalAccess($user)));
        });
    }

    /**
     * The internal documents this person may see, by id.
     *
     * `documents.see_internal` is a scoped grant, so the answer differs per
     * project — which a single WHERE cannot express. The set is small (internal
     * documents are the exception), so it is resolved in PHP and fed back in.
     *
     * @return array<int, int>
     */
    protected static function idsWithInternalAccess(User $user): array
    {
        $resolver = app(\App\Services\PermissionResolver::class);

        return static::query()
            ->where('is_internal', true)
            ->get(['id', 'project_id', 'job_site_id'])
            ->filter(fn (self $document) => $resolver->allows($user, 'documents.see_internal', $document))
            ->pluck('id')
            ->all();
    }

    /**
     * Documents whose first upload actually completed. A document whose only
     * version is still uploading must not appear in a list as an empty row.
     */
    public function scopeReady(Builder $query): Builder
    {
        return $query->whereNotNull('current_version_id');
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    public function isProjectLevel(): bool
    {
        return is_null($this->job_site_id);
    }

    public function locationLabel(): string
    {
        return $this->jobSite?->name ?? __('Project (General)');
    }

    public function categoryLabel(): string
    {
        return __($this->category->label());
    }

    public function extension(): string
    {
        return strtolower(pathinfo($this->name, PATHINFO_EXTENSION));
    }

    public function formattedSize(): string
    {
        return DocumentSettings::formatBytes($this->current_size_bytes);
    }

    public function isImage(): bool
    {
        return in_array($this->extension(), ['jpg', 'jpeg', 'png', 'webp', 'gif', 'heic'], true);
    }

    public function isPdf(): bool
    {
        return $this->extension() === 'pdf';
    }

    public function isVideo(): bool
    {
        return in_array($this->extension(), ['mp4', 'mov'], true);
    }

    /**
     * Can this be shown in the browser, or only downloaded?
     */
    public function isPreviewable(): bool
    {
        return $this->isPdf() || $this->isImage() || $this->isVideo();
    }

    public function hasVersionHistory(): bool
    {
        return $this->current_version_number > 1;
    }

    /**
     * May this user see the document at all?
     *
     * N5 (docs/permissions-notes.md): this used to return **true for every
     * non-internal document, to anybody** — so any signed-in person could
     * fetch any project's files by guessing an id, and the download route was
     * behind `auth` and nothing else. Two questions now: may they open
     * documents on the project this one belongs to, and — if it is flagged
     * internal — may they see those.
     */
    public function isVisibleTo(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        $resolver = app(\App\Services\PermissionResolver::class);

        if (! $resolver->allows($user, 'documents.view', $this)) {
            return false;
        }

        return ! $this->is_internal
            || $resolver->allows($user, 'documents.see_internal', $this);
    }
}
