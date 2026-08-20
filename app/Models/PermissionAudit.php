<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Auth;

/**
 * Who changed whose access, when, and to what. Written by the permission
 * screens; never deleted with the membership it describes.
 */
class PermissionAudit extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'actor_id',
        'subject_user_id',
        'scopeable_type',
        'scopeable_id',
        'subject_type',
        'subject_id',
        'action',
        'summary',
        'before',
        'after',
    ];

    protected $casts = [
        'before' => 'array',
        'after' => 'array',
        'created_at' => 'datetime',
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function subjectUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subject_user_id');
    }

    public function scopeable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Record one change. The actor defaults to whoever is signed in.
     */
    public static function record(
        string $subjectType,
        ?int $subjectId,
        string $action,
        ?string $summary = null,
        ?int $subjectUserId = null,
        ?Model $scopeable = null,
        ?array $before = null,
        ?array $after = null,
    ): self {
        return static::create([
            'actor_id' => Auth::id(),
            'subject_user_id' => $subjectUserId,
            'scopeable_type' => $scopeable ? $scopeable::class : null,
            'scopeable_id' => $scopeable?->getKey(),
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'action' => $action,
            'summary' => $summary,
            'before' => $before,
            'after' => $after,
        ]);
    }
}
