<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A correction made to a minute after it was published. Shown on the document
 * and in the PDF — a record that changes silently is worth nothing.
 */
class MeetingRevision extends Model
{
    protected $fillable = [
        'meeting_id',
        'revision_number',
        'revised_by',
        'reason',
        'changes',
    ];

    protected $casts = [
        'changes' => 'array',
        'revision_number' => 'integer',
    ];

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function revisedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revised_by');
    }
}
