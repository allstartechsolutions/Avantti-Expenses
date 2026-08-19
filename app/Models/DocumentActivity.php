<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * The audit trail behind every document. Public share access has no signed-in
 * user, which is why user_id is nullable and the IP is recorded.
 */
class DocumentActivity extends Model
{
    public const UPLOADED = 'uploaded';
    public const VERSION_ADDED = 'version_added';
    public const RENAMED = 'renamed';
    public const MOVED = 'moved';
    public const RECATEGORISED = 'recategorised';
    public const TAGGED = 'tagged';
    public const DOWNLOADED = 'downloaded';
    public const PREVIEWED = 'previewed';
    public const DELETED = 'deleted';
    public const RESTORED = 'restored';
    public const PURGED = 'purged';
    public const SHARED = 'shared';
    public const SHARE_REVOKED = 'share_revoked';
    public const SHARE_ACCESSED = 'share_accessed';
    public const FOLDER_CREATED = 'folder_created';
    public const FOLDER_RENAMED = 'folder_renamed';
    public const FOLDER_DELETED = 'folder_deleted';

    protected $fillable = [
        'document_id',
        'folder_id',
        'share_id',
        'action',
        'context',
        'user_id',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'context' => 'array',
    ];

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

    public function share(): BelongsTo
    {
        return $this->belongsTo(DocumentShare::class, 'share_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // =========================================================================
    // WRITING
    // =========================================================================

    /**
     * Record one action. The request details are captured here so no caller
     * has to remember to pass them.
     *
     * @param  array<string, mixed>  $context
     */
    public static function record(string $action, array $attributes = [], array $context = []): self
    {
        return static::create(array_merge([
            'action' => $action,
            'context' => $context ?: null,
            'user_id' => Auth::id(),
            'ip_address' => Request::ip(),
            'user_agent' => substr((string) Request::userAgent(), 0, 512),
        ], $attributes));
    }

    /**
     * A sentence for the history panel.
     */
    public function label(): string
    {
        return match ($this->action) {
            self::UPLOADED => __('Uploaded'),
            self::VERSION_ADDED => __('New version uploaded'),
            self::RENAMED => __('Renamed'),
            self::MOVED => __('Moved'),
            self::RECATEGORISED => __('Category changed'),
            self::TAGGED => __('Tags changed'),
            self::DOWNLOADED => __('Downloaded'),
            self::PREVIEWED => __('Previewed'),
            self::DELETED => __('Deleted'),
            self::RESTORED => __('Restored'),
            self::PURGED => __('Permanently deleted'),
            self::SHARED => __('Share link created'),
            self::SHARE_REVOKED => __('Share link revoked'),
            self::SHARE_ACCESSED => __('Opened through a share link'),
            self::FOLDER_CREATED => __('Folder created'),
            self::FOLDER_RENAMED => __('Folder renamed'),
            self::FOLDER_DELETED => __('Folder deleted'),
            default => $this->action,
        };
    }
}
