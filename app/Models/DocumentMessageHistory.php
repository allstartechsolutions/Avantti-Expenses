<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentMessageHistory extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'document_message_id',
        'action',
        'field_changed',
        'old_value',
        'new_value',
        'changed_by',
    ];

    public function documentMessage(): BelongsTo
    {
        return $this->belongsTo(DocumentMessage::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
