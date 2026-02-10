<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class DocumentMessage extends Model
{
    protected $fillable = [
        'title',
        'body',
        'type',
        'is_default',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(DocumentMessageHistory::class);
    }

    public static function logHistory(?int $messageId, string $action, ?string $field = null, ?string $oldValue = null, ?string $newValue = null): void
    {
        DocumentMessageHistory::create([
            'document_message_id' => $messageId,
            'action' => $action,
            'field_changed' => $field,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'changed_by' => Auth::id(),
        ]);
    }
}
