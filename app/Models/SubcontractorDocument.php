<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Services\DocumentStorageService;
use Illuminate\Support\Facades\Storage;

/**
 * A compliance document filed against a vendor — insurance, licence, tax
 * clearance — with the date it stops being good.
 *
 * Two independent things are tracked on a row, and they must not be
 * confused:
 *
 * - **Lifecycle** (`status`): `active`, `superseded` by a renewal, or
 *   `archived` because it is no longer required. Only active documents count
 *   for anything — the badge, the reminders, the "what is missing" list.
 * - **Expiry** (`expiry_status`, derived): valid, expiring soon, expired —
 *   computed from the date, and only meaningful on an active document.
 */
class SubcontractorDocument extends Model
{
    /** Days before the expiration date at which a document is flagged as expiring soon. */
    public const EXPIRING_SOON_DAYS = 30;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_SUPERSEDED = 'superseded';
    public const STATUS_ARCHIVED = 'archived';

    public const EXPIRY_VALID = 'valid';
    public const EXPIRY_EXPIRING_SOON = 'expiring_soon';
    public const EXPIRY_EXPIRED = 'expired';
    /** The type asks for a date and this document carries none — it cannot be watched. */
    public const EXPIRY_UNDATED = 'undated';

    protected $fillable = [
        'subcontractor_id',
        'document_type_id',
        'file_path',
        'file_name',
        'file_size',
        'file_upload_id',
        'expiration_date',
        'notes',
        'uploaded_by',
    ];

    protected $casts = [
        'expiration_date' => 'date',
        'file_size' => 'integer',
        'archived_at' => 'datetime',
        'notified_30_at' => 'datetime',
        'notified_15_at' => 'datetime',
        'notified_7_at' => 'datetime',
        'notified_expired_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => self::STATUS_ACTIVE,
    ];

    /*
    |---------------------------------------------------------------------------
    | Relationships
    |---------------------------------------------------------------------------
    */

    public function subcontractor(): BelongsTo
    {
        return $this->belongsTo(Subcontractor::class);
    }

    /**
     * The owning vendor row without the subcontractor flag scope. A vendor
     * that stops being a subcontractor keeps its documents (see
     * SubcontractorShow::deleteSubcontractor), so `subcontractor()` can be
     * null for a row that still exists.
     */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'subcontractor_id');
    }

    public function documentType(): BelongsTo
    {
        return $this->belongsTo(DocumentType::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * The stored file, for a document filed through the shared upload path.
     * Null on a legacy row, whose bytes are at `file_path` on the private
     * disk. `downloadUrl()` hides the difference.
     */
    public function fileUpload(): BelongsTo
    {
        return $this->belongsTo(FileUpload::class);
    }

    public function downloadUrl(): string
    {
        return route('subcontractors.documents.download', [$this->subcontractor_id, $this->id]);
    }

    public function archivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by');
    }

    /** The renewal that replaced this document, when it has been superseded. */
    public function supersededBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'superseded_by_id');
    }

    /** The document this one replaced, when it is a renewal. */
    public function supersedes(): HasOne
    {
        return $this->hasOne(self::class, 'superseded_by_id');
    }

    /*
    |---------------------------------------------------------------------------
    | Scopes
    |---------------------------------------------------------------------------
    */

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Documents that are watched: dated, under an **active** type that asks
     * for a date, and owned by a vendor still flagged as a subcontractor.
     *
     * A retired type takes its documents out of the watch — that is what
     * retiring is for — and a vendor that stopped being a subcontractor keeps
     * its files but is nobody's compliance problem any more.
     *
     * Plain comparisons on the DATE column, never `whereDate()`: the latter
     * wraps the column in `date()` and defeats the index the list relies on.
     */
    public function scopeRequiringExpiry(Builder $query): Builder
    {
        return $query
            ->whereNotNull('expiration_date')
            ->whereIn('document_type_id', DocumentType::query()
                ->select('id')
                ->where('requires_expiration', true)
                ->where('is_active', true))
            ->whereIn('subcontractor_id', Vendor::query()
                ->select('id')
                ->where('is_subcontractor', true));
    }

    /** Active, watched documents already past their date. */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->active()->requiringExpiry()
            ->where('expiration_date', '<', Carbon::today()->toDateString());
    }

    /**
     * Active, watched documents due within the next N days (today included,
     * the past excluded). The upper bound is "before the day after", not
     * "on or before the day": sqlite keeps the date cast with a time suffix,
     * and a same-day string bound would exclude it there.
     */
    public function scopeExpiringWithin(Builder $query, int $days = self::EXPIRING_SOON_DAYS): Builder
    {
        return $query->active()->requiringExpiry()
            ->where('expiration_date', '>=', Carbon::today()->toDateString())
            ->where('expiration_date', '<', Carbon::today()->addDays($days + 1)->toDateString());
    }

    /*
    |---------------------------------------------------------------------------
    | Lifecycle
    |---------------------------------------------------------------------------
    */

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isSuperseded(): bool
    {
        return $this->status === self::STATUS_SUPERSEDED;
    }

    public function isArchived(): bool
    {
        return $this->status === self::STATUS_ARCHIVED;
    }

    /**
     * Retire this document in favour of its renewal. The replacement is
     * already saved; this row only steps aside and points at it.
     */
    public function supersedeWith(self $replacement): void
    {
        $this->forceFill([
            'status' => self::STATUS_SUPERSEDED,
            'superseded_by_id' => $replacement->id,
        ])->save();
    }

    /** Take the document out of the expiry watch because it is no longer required. */
    public function archive(User $by, string $reason): void
    {
        $this->forceFill([
            'status' => self::STATUS_ARCHIVED,
            'archived_at' => now(),
            'archived_by' => $by->id,
            'archive_reason' => $reason,
        ])->save();
    }

    /** Put an archived document back into service. */
    public function reactivate(): void
    {
        $this->forceFill([
            'status' => self::STATUS_ACTIVE,
            'archived_at' => null,
            'archived_by' => null,
            'archive_reason' => null,
        ])->save();
    }

    public function getStatusLabelAttribute(): string
    {
        return self::statusLabel($this->status);
    }

    public static function statusLabel(?string $status): string
    {
        return match ($status) {
            self::STATUS_SUPERSEDED => __('Document superseded'),
            self::STATUS_ARCHIVED => __('Document archived'),
            default => __('Document active'),
        };
    }

    /*
    |---------------------------------------------------------------------------
    | Expiry
    |---------------------------------------------------------------------------
    */

    /**
     * Where this document stands against its date.
     *
     * A document that is not active, or whose type does not ask for a date or
     * has been retired, is simply valid — nothing downstream has to
     * special-case it. A document under a live dated type that carries **no**
     * date is `undated`: it exists, but it cannot be watched, and it must not
     * pass for current.
     */
    public function getExpiryStatusAttribute(): string
    {
        if (! $this->isActive() || ! $this->documentType?->requires_expiration || ! $this->documentType->is_active) {
            return self::EXPIRY_VALID;
        }

        if (! $this->expiration_date) {
            return self::EXPIRY_UNDATED;
        }

        $expiration = $this->expiration_date->copy()->startOfDay();

        if ($expiration->lt(Carbon::today())) {
            return self::EXPIRY_EXPIRED;
        }

        if ($expiration->lte(Carbon::today()->addDays(self::EXPIRING_SOON_DAYS))) {
            return self::EXPIRY_EXPIRING_SOON;
        }

        return self::EXPIRY_VALID;
    }

    /** Whole days until the date; negative once it has passed, null when there is no date. */
    public function getDaysUntilExpiryAttribute(): ?int
    {
        if (! $this->expiration_date) {
            return null;
        }

        return (int) Carbon::today()->diffInDays($this->expiration_date->copy()->startOfDay(), false);
    }

    public function getExpiryStatusLabelAttribute(): string
    {
        return self::expiryStatusLabel($this->expiry_status);
    }

    public static function expiryStatusLabel(?string $status): string
    {
        return match ($status) {
            self::EXPIRY_EXPIRED => __('Document expired'),
            self::EXPIRY_EXPIRING_SOON => __('Document expiring soon'),
            self::EXPIRY_UNDATED => __('No expiration date'),
            default => __('Document valid'),
        };
    }

    /**
     * The worst of several expiry states: expired beats expiring beats
     * undated beats valid. One place for the ranking, used by the page, the
     * badge and the mail.
     */
    public static function worstExpiry(iterable $states): string
    {
        $rank = [self::EXPIRY_EXPIRED => 3, self::EXPIRY_EXPIRING_SOON => 2, self::EXPIRY_UNDATED => 1, self::EXPIRY_VALID => 0];
        $worst = self::EXPIRY_VALID;

        foreach ($states as $state) {
            if (($rank[$state] ?? 0) > $rank[$worst]) {
                $worst = $state;
            }
        }

        return $worst;
    }

    /** Which reminder stages have gone out for this document, most recent last. */
    public function getRemindedStagesAttribute(): array
    {
        return collect([
            30 => $this->notified_30_at,
            15 => $this->notified_15_at,
            7 => $this->notified_7_at,
            'expired' => $this->notified_expired_at,
        ])->filter()->keys()->all();
    }

    /*
    |---------------------------------------------------------------------------
    | Presentation
    |---------------------------------------------------------------------------
    */

    public function getFormattedFileSizeAttribute(): string
    {
        if (! $this->file_size) {
            return __('N/A');
        }

        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2).' '.$units[$i];
    }

    /**
     * Delete the file when the model is deleted — the stored object and its
     * row for an uploaded file, the path on the private disk for a legacy
     * one — and give the document this one replaced its place back: deleting
     * a renewal that turned out to be the wrong file must not leave the
     * previous certificate stranded in the history with nothing in force.
     */
    protected static function booted(): void
    {
        static::deleting(function (SubcontractorDocument $document) {
            if ($file = $document->fileUpload) {
                app(DocumentStorageService::class)->deleteObject($file);
                $file->forceDelete();
            } elseif ($document->file_path && Storage::disk('local')->exists($document->file_path)) {
                Storage::disk('local')->delete($document->file_path);
            }

            static::query()
                ->where('superseded_by_id', $document->id)
                ->where('status', self::STATUS_SUPERSEDED)
                ->update(['status' => self::STATUS_ACTIVE, 'superseded_by_id' => null]);
        });
    }
}
