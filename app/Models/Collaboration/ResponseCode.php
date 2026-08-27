<?php

namespace App\Models\Collaboration;

use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A coded answer: "B — Aprovado com comentários".
 *
 * The letter and the wording are presentation. `canonical` is the meaning, and
 * it is the only thing business logic is allowed to read — a company that
 * renames C to R, or an install running the US set, must not change what the
 * code does.
 */
class ResponseCode extends Model
{
    protected $table = 'collaboration_response_codes';

    protected $fillable = [
        'project_id',
        'market',
        'document_type',
        'code',
        'label_key',
        'canonical',
        'closes_cycle',
        'sort',
    ];

    protected $casts = [
        'closes_cycle' => 'boolean',
        'sort' => 'integer',
    ];

    /*
    |---------------------------------------------------------------------------
    | The canonical meanings
    |---------------------------------------------------------------------------
    | Branch on these. Never on `code`, never on the label.
    */

    public const APPROVED = 'approved';
    public const APPROVED_AS_NOTED = 'approved_as_noted';
    public const REVISE_RESUBMIT = 'revise_resubmit';
    public const REJECTED = 'rejected';
    public const FOR_RECORD_ONLY = 'for_record_only';

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /*
    |---------------------------------------------------------------------------
    | Scopes
    |---------------------------------------------------------------------------
    */

    /**
     * The codes on offer for one document type on one project.
     *
     * The market is not a parameter. An installation serves one company in one
     * country, so it comes from `config('app.country')` and there is nothing
     * for a caller to get wrong (docs/rfi-aprovacoes-discovery.md item 1).
     */
    public function scopeFor(Builder $query, string $documentType, ?int $projectId = null): Builder
    {
        return $query
            ->where('document_type', $documentType)
            ->where('market', static::market())
            ->where(fn (Builder $q) => $q->whereNull('project_id')->orWhere('project_id', $projectId))
            ->orderBy('sort');
    }

    /** 'br' or 'us', from the install's country. */
    public static function market(): string
    {
        return strtolower(config('app.country', 'US')) === 'br' ? 'br' : 'us';
    }

    /**
     * The codes to offer, with a project's own overriding the defaults.
     *
     * A project-scoped row replaces the global one carrying the same
     * `canonical`, rather than appearing beside it — otherwise a project that
     * renamed one code would offer the reviewer two ways to say the same thing.
     *
     * @return \Illuminate\Support\Collection<int, static>
     */
    public static function offered(string $documentType, ?int $projectId = null)
    {
        return static::query()
            ->for($documentType, $projectId)
            ->get()
            ->sortByDesc(fn (self $code) => $code->project_id !== null)
            ->unique('canonical')
            ->sortBy('sort')
            ->values();
    }

    /*
    |---------------------------------------------------------------------------
    | Presentation
    |---------------------------------------------------------------------------
    */

    /** "C — Reapresentar". */
    public function getLabel(): string
    {
        return $this->code.' — '.__($this->label_key);
    }

    /** True when recording this code ends the revision cycle. */
    public function closesCycle(): bool
    {
        return $this->closes_cycle;
    }

    /** True when recording it opens the next revision instead. */
    public function opensRevision(): bool
    {
        return $this->canonical === self::REVISE_RESUBMIT;
    }
}
