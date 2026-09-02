<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class SubcontractorDocument extends Model
{
    /** Days before the expiration date at which a document is flagged as expiring soon. */
    public const EXPIRING_SOON_DAYS = 30;

    protected $fillable = [
        'subcontractor_id',
        'document_type_id',
        'file_path',
        'file_name',
        'file_size',
        'expiration_date',
        'notes',
        'uploaded_by',
    ];

    protected $casts = [
        'expiration_date' => 'date',
        'file_size' => 'integer',
    ];

    /**
     * Get the subcontractor that owns this document
     */
    public function subcontractor(): BelongsTo
    {
        return $this->belongsTo(Subcontractor::class);
    }

    /**
     * Get the document type
     */
    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    /**
     * Get the user who uploaded this document
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Get the document status
     */
    public function getStatusAttribute(): string
    {
        if (!$this->documentType->requires_expiration || !$this->expiration_date) {
            return 'valid';
        }

        $expiration = $this->expiration_date->startOfDay();

        if ($expiration->lt(Carbon::today())) {
            return 'expired';
        }

        if ($expiration->lte(Carbon::today()->addDays(self::EXPIRING_SOON_DAYS))) {
            return 'expiring_soon';
        }

        return 'valid';
    }

    /**
     * Get status label for display
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'expired' => __('Document expired'),
            'expiring_soon' => __('Document expiring soon'),
            default => __('Document valid'),
        };
    }

    /**
     * Get status color for UI
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'expired' => 'red',
            'expiring_soon' => 'yellow',
            'valid' => 'green',
            default => 'green',
        };
    }

    /**
     * Get formatted file size
     */
    public function getFormattedFileSizeAttribute(): string
    {
        if (!$this->file_size) {
            return 'N/A';
        }

        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Delete the file when the model is deleted
     */
    protected static function booted(): void
    {
        static::deleting(function (SubcontractorDocument $document) {
            if ($document->file_path && Storage::disk('local')->exists($document->file_path)) {
                Storage::disk('local')->delete($document->file_path);
            }
        });
    }
}
