<?php

namespace App\Contracts;

/**
 * A row that stands for one object in storage.
 *
 * The document repository's presign / multipart / verify machinery in
 * DocumentStorageService works on any model shaped like this, so a task's
 * attachment goes up the same way a two-gigabyte drawing set does.
 *
 * Implementations are Eloquent models and must carry these columns:
 *   disk, object_key, original_name, size_bytes, mime_type,
 *   upload_status, multipart_upload_id
 *
 * Implemented by DocumentVersion (the repository) and FileUpload (everything
 * else). See docs/meetings-module-plan.md §7.
 */
interface StoredFile
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_AVAILABLE = 'available';
    public const STATUS_FAILED = 'failed';

    /** Waiting for the browser to finish pushing bytes. */
    public function isPending(): bool;

    /** Uploaded and verified — the only state anything may serve. */
    public function isAvailable(): bool;

    /** Going up in parts, so completing it means closing the multipart. */
    public function isMultipart(): bool;
}
