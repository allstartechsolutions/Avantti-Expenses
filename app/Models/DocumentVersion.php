<?php

namespace App\Models;

use App\Services\DocumentSettings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One uploaded file behind a Document. Rows are created before the browser
 * starts pushing bytes to storage, so a version is only real once its status
 * is 'available'.
 */
class DocumentVersion extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_AVAILABLE = 'available';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'document_id',
        'version_number',
        'disk',
        'object_key',
        'original_name',
        'size_bytes',
        'mime_type',
        'checksum',
        'notes',
        'upload_status',
        'multipart_upload_id',
        'uploaded_by',
    ];

    protected $casts = [
        'version_number' => 'integer',
        'size_bytes' => 'integer',
    ];

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    public function isAvailable(): bool
    {
        return $this->upload_status === self::STATUS_AVAILABLE;
    }

    public function isPending(): bool
    {
        return $this->upload_status === self::STATUS_PENDING;
    }

    public function isMultipart(): bool
    {
        return filled($this->multipart_upload_id);
    }

    public function formattedSize(): string
    {
        return DocumentSettings::formatBytes($this->size_bytes);
    }
}
