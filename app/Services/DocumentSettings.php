<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Answers the questions the document repository asks about its own
 * configuration: which disk it is on, how large an upload may be, and whether
 * a given file is acceptable. Everything reads config/documents.php — the
 * module never names a disk directly, so an install with no Cloudflare R2
 * credentials keeps working on the local private disk.
 */
class DocumentSettings
{
    /**
     * The disk the repository stores documents on.
     */
    public static function disk(): string
    {
        $disk = (string) config('documents.disk', 'local');

        // A misconfigured install must not lose files into a disk that does
        // not exist: fall back to the private local disk instead.
        if (! config("filesystems.disks.{$disk}")) {
            return 'local';
        }

        if ($disk === 'r2' && ! self::isCloudConfigured()) {
            return 'local';
        }

        return $disk;
    }

    /**
     * True when R2 is selected and every credential it needs is present. This
     * decides whether the browser uploads straight to the bucket or the file
     * travels through PHP.
     */
    public static function isCloudConfigured(): bool
    {
        if ((string) config('documents.disk') !== 'r2') {
            return false;
        }

        foreach (['key', 'secret', 'bucket', 'endpoint'] as $key) {
            if (blank(config("filesystems.disks.r2.{$key}"))) {
                return false;
            }
        }

        return true;
    }

    /**
     * The largest upload the install can actually accept.
     *
     * On R2 that is the configured ceiling, because the bytes never touch the
     * web server. On the local disk it is whichever PHP limit bites first —
     * promising 5 GB and failing at 90% would be a lie the screen tells.
     */
    public static function maxUploadBytes(): int
    {
        $configured = (int) config('documents.max_upload_bytes');

        if (self::isCloudConfigured()) {
            return $configured;
        }

        $phpLimit = min(
            self::phpIniBytes('upload_max_filesize'),
            self::phpIniBytes('post_max_size')
        );

        return min($configured, $phpLimit);
    }

    /**
     * Whether an upload of this size needs S3 multipart rather than a single
     * presigned PUT.
     */
    public static function needsMultipart(int $sizeBytes): bool
    {
        return $sizeBytes > (int) config('documents.multipart_threshold');
    }

    public static function partSize(): int
    {
        return (int) config('documents.part_size');
    }

    /**
     * Is this extension/mime pair something we accept?
     */
    public static function isAllowedFile(string $fileName, ?string $mimeType = null): bool
    {
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if ($extension === '' || in_array($extension, config('documents.blocked_extensions', []), true)) {
            return false;
        }

        $allowed = config('documents.allowed_extensions', []);

        if (! array_key_exists($extension, $allowed)) {
            return false;
        }

        // Browsers are inconsistent about mime types for CAD and archive
        // formats, so a blank or unknown mime does not fail the extension
        // check — the extension allowlist is the guard that matters.
        if (blank($mimeType) || $mimeType === 'application/octet-stream') {
            return true;
        }

        return in_array($mimeType, $allowed[$extension], true);
    }

    /**
     * Extensions the file picker should offer, as ".pdf,.jpg,…".
     */
    public static function acceptAttribute(): string
    {
        return collect(array_keys(config('documents.allowed_extensions', [])))
            ->map(fn (string $extension) => '.'.$extension)
            ->implode(',');
    }

    /**
     * A file name safe to put in an object key: no directories, no control
     * characters, no leading dots, and short enough for any storage backend.
     */
    public static function sanitizeFileName(string $fileName): string
    {
        $fileName = basename(str_replace('\\', '/', $fileName));

        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $name = pathinfo($fileName, PATHINFO_FILENAME);

        $name = Str::of($name)
            ->ascii()
            ->replaceMatches('/[^A-Za-z0-9 ._-]/', '-')
            ->replaceMatches('/-+/', '-')
            ->trim(' .-')
            ->limit(120, '')
            ->value();

        if ($name === '') {
            $name = 'file';
        }

        return $extension === '' ? $name : "{$name}.{$extension}";
    }

    /**
     * The storage key for one version of one document. Keys are generated
     * here and never derived from anything the user typed as a path.
     */
    public static function objectKey(int $projectId, string $documentUuid, int $versionNumber, string $fileName): string
    {
        return sprintf(
            'projects/%d/documents/%s/v%d/%s',
            $projectId,
            $documentUuid,
            $versionNumber,
            self::sanitizeFileName($fileName)
        );
    }

    public static function presignTtl(): int
    {
        return (int) config('documents.presign_ttl', 300);
    }

    public static function storageQuotaBytes(): ?int
    {
        $quota = config('documents.storage_quota_bytes');

        return blank($quota) ? null : (int) $quota;
    }

    /**
     * Everything the install is storing, which is what the quota is measured
     * against — not one project's share of it.
     */
    public static function installUsedBytes(): int
    {
        return (int) \App\Models\Document::withTrashed()->sum('current_size_bytes');
    }

    /**
     * Would this upload put the install over its ceiling? Null quota means no.
     */
    public static function wouldExceedQuota(int $incomingBytes): bool
    {
        $quota = self::storageQuotaBytes();

        return $quota !== null && (self::installUsedBytes() + $incomingBytes) > $quota;
    }

    /**
     * "1.4 GB" — used everywhere a size is shown.
     */
    public static function formatBytes(?int $bytes, int $precision = 1): string
    {
        $bytes = (int) $bytes;

        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = min((int) floor(log($bytes, 1024)), count($units) - 1);
        $value = $bytes / (1024 ** $power);

        return round($value, $power === 0 ? 0 : $precision).' '.$units[$power];
    }

    /**
     * Turn a php.ini shorthand size ("2M", "512K", "1G") into bytes.
     */
    private static function phpIniBytes(string $directive): int
    {
        $value = trim((string) ini_get($directive));

        // Unlimited. PHP treats -1 and 0 that way, and reporting them as zero
        // would make min() pick "no limit at all" as the smallest limit.
        if ($value === '' || $value === '-1' || $value === '0') {
            return PHP_INT_MAX;
        }

        $unit = strtolower(substr($value, -1));
        $number = (int) $value;

        return match ($unit) {
            'g' => $number * 1024 * 1024 * 1024,
            'm' => $number * 1024 * 1024,
            'k' => $number * 1024,
            default => $number,
        };
    }
}
