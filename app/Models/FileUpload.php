<?php

namespace App\Models;

use App\Contracts\StoredFile;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * A file uploaded straight to the configured documents disk (R2 in
 * production) by the same presigned pipeline the document repository uses.
 *
 * The cloud sibling of Attachment, which keeps small files on the local
 * private disk for expenses, POs, requisitions and quotations. Deliberately
 * generic — any model can own these.
 *
 * See docs/meetings-module-plan.md §7.
 */
class FileUpload extends Model implements StoredFile
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'attachable_type',
        'attachable_id',
        'disk',
        'object_key',
        'original_name',
        'size_bytes',
        'mime_type',
        'checksum',
        'upload_status',
        'multipart_upload_id',
        'document_id',
        'uploaded_by',
    ];

    protected $attributes = [
        'upload_status' => 'pending',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (self $file) {
            $file->uuid ??= (string) Str::uuid();
        });
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    /** Set once the file has also been filed into the project repository. */
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

    public function isFiledToRepository(): bool
    {
        return $this->document_id !== null;
    }

    public function extension(): string
    {
        return strtolower(pathinfo($this->original_name, PATHINFO_EXTENSION));
    }

    public function isImage(): bool
    {
        return in_array($this->extension(), ['jpg', 'jpeg', 'png', 'webp', 'gif', 'heic'], true);
    }

    public function isPdf(): bool
    {
        return $this->extension() === 'pdf';
    }

    /** Human size, the way the repository shows it. */
    public function formattedSize(): string
    {
        $bytes = (int) $this->size_bytes;

        if ($bytes < 1024) {
            return $bytes.' B';
        }

        foreach (['KB', 'MB', 'GB', 'TB'] as $index => $unit) {
            $value = $bytes / (1024 ** ($index + 1));

            if ($value < 1024) {
                return round($value, $value < 10 ? 1 : 0).' '.$unit;
            }
        }

        return round($bytes / (1024 ** 4), 1).' TB';
    }
}
