<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One reply to an SI.
 *
 * Replies accumulate rather than overwrite: an SI is answered by whoever can
 * answer it, and on a real job that is often more than one person. Which of
 * them the work is built to is `Rfi::validReply()`, decided by a person.
 */
class RfiReply extends Model
{
    protected $fillable = [
        'rfi_id',
        'body',
        'replied_by_id',
        'replied_at',
        'edited_by_id',
        'edited_at',
    ];

    protected $casts = [
        'replied_at' => 'datetime',
        'edited_at' => 'datetime',
    ];

    public function rfi(): BelongsTo
    {
        return $this->belongsTo(Rfi::class);
    }

    public function repliedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'replied_by_id');
    }

    public function editedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'edited_by_id');
    }

    /** What was attached when this reply was given. */
    public function files(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(FileUpload::class, 'attachable');
    }

    public function availableFiles(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->files()->where('upload_status', FileUpload::STATUS_AVAILABLE);
    }

    /** Whether this is the reply the SI is answered by. */
    public function isValid(): bool
    {
        return $this->rfi?->valid_reply_id === $this->id;
    }

    public function wasEdited(): bool
    {
        return $this->edited_at !== null;
    }

    /** Who said it — or "Removed user" once the account is gone. */
    public function getAuthorName(): string
    {
        return $this->repliedBy?->name ?? __('collaboration.label.removed_user');
    }
}
