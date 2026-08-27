<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One person's place in the review of one revision.
 *
 * Staff or guest — in Brazil the reviewer is normally the external projetista,
 * which the guest system already supports.
 */
class ApprovalReviewer extends Model
{
    protected $fillable = [
        'approval_revision_id',
        'user_id',
        'sequence',
        'role',
        'responded_at',
    ];

    protected $casts = [
        'sequence' => 'integer',
        'responded_at' => 'datetime',
    ];

    public function revision(): BelongsTo
    {
        return $this->belongsTo(ApprovalRevision::class, 'approval_revision_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hasResponded(): bool
    {
        return $this->responded_at !== null;
    }
}
