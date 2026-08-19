<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * An expiring public link to a document or a folder, for clients and vendors
 * who have no login. Exactly one of document_id / folder_id is set.
 */
class DocumentShare extends Model
{
    protected $fillable = [
        'document_id',
        'folder_id',
        'token',
        'recipient_label',
        'expires_at',
        'password_hash',
        'allow_download',
        'max_downloads',
        'download_count',
        'last_accessed_at',
        'revoked_at',
        'revoked_by',
        'created_by',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'last_accessed_at' => 'datetime',
        'revoked_at' => 'datetime',
        'allow_download' => 'boolean',
        'max_downloads' => 'integer',
        'download_count' => 'integer',
    ];

    /**
     * The password is never readable, and the raw token is never guessable.
     */
    protected $hidden = [
        'password_hash',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (self $share) {
            $share->token ??= self::generateToken();
        });
    }

    public static function generateToken(): string
    {
        return Str::random(48);
    }

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(DocumentFolder::class, 'folder_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(DocumentActivity::class, 'share_id')->latest();
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at')
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    // =========================================================================
    // STATE
    // =========================================================================

    public function isRevoked(): bool
    {
        return filled($this->revoked_at);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isExhausted(): bool
    {
        return $this->max_downloads && $this->download_count >= $this->max_downloads;
    }

    public function isUsable(): bool
    {
        return ! $this->isRevoked() && ! $this->isExpired() && ! $this->isExhausted();
    }

    /**
     * Why the link no longer works — the public page says this instead of a
     * bare 404, so the recipient knows to ask for a new link.
     */
    public function unusableReason(): ?string
    {
        return match (true) {
            $this->isRevoked() => __('This link has been revoked.'),
            $this->isExpired() => __('This link expired on :date.', ['date' => $this->expires_at->format('d/m/Y')]),
            $this->isExhausted() => __('This link has reached its download limit.'),
            default => null,
        };
    }

    public function requiresPassword(): bool
    {
        return filled($this->password_hash);
    }

    public function checkPassword(?string $password): bool
    {
        if (! $this->requiresPassword()) {
            return true;
        }

        return filled($password) && Hash::check($password, $this->password_hash);
    }

    public function isFolderShare(): bool
    {
        return filled($this->folder_id);
    }

    public function remainingDownloads(): ?int
    {
        return $this->max_downloads
            ? max(0, $this->max_downloads - $this->download_count)
            : null;
    }

    public function publicUrl(): string
    {
        return route('documents.share', $this->token);
    }
}
