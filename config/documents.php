<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Storage Disk
    |--------------------------------------------------------------------------
    |
    | Which disk the document repository writes to. Set DOCUMENTS_DISK=r2 on
    | installs with Cloudflare R2 configured; anything else keeps documents on
    | the local private disk. The module never names a disk directly — it reads
    | this key — so an install without R2 still works, with the smaller upload
    | ceiling that pushing files through PHP imposes.
    |
    */

    'disk' => env('DOCUMENTS_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Upload Limits
    |--------------------------------------------------------------------------
    |
    | 'max_upload_bytes' is the cloud ceiling: uploads go straight from the
    | browser to R2 in parts, so PHP's limits do not apply. On the local disk
    | the effective ceiling is whichever of upload_max_filesize / post_max_size
    | is smaller, resolved at runtime by DocumentSettings::maxUploadBytes().
    |
    | R2 requires every part of a multipart upload to be the same size except
    | the last, with a 5 MB minimum and 10 000 parts maximum. 64 MB parts give
    | a 640 GB ceiling and keep the request count sane.
    |
    */

    'max_upload_bytes' => (int) env('DOCUMENTS_MAX_UPLOAD', 5 * 1024 * 1024 * 1024),

    'part_size' => 64 * 1024 * 1024,

    'multipart_threshold' => 100 * 1024 * 1024,

    /*
    |--------------------------------------------------------------------------
    | Signed URL Lifetime
    |--------------------------------------------------------------------------
    |
    | How long a presigned download or preview URL stays valid, in seconds.
    |
    | The signature in the URL is the credential: anyone holding the link can
    | fetch that one file until it expires, so the window is kept short. It is
    | checked when the request starts, so a large download already running is
    | not cut off when the clock runs out.
    |
    */

    'presign_ttl' => (int) env('DOCUMENTS_PRESIGN_TTL', 60),

    /*
    |--------------------------------------------------------------------------
    | Retention
    |--------------------------------------------------------------------------
    |
    | Days a soft-deleted document stays recoverable in the Trash before the
    | purge command removes it and its objects for good. Null disables the
    | automatic purge — deleted documents then wait for a manual purge.
    |
    */

    'retention_days' => env('DOCUMENTS_RETENTION_DAYS', 30),

    /*
    | Hours an unfinished multipart upload may sit before the prune command
    | aborts it. Incomplete uploads are billed by R2 until aborted.
    */

    'stale_upload_hours' => 24,

    /*
    |--------------------------------------------------------------------------
    | Share Links
    |--------------------------------------------------------------------------
    */

    'share_default_days' => (int) env('DOCUMENTS_SHARE_DAYS', 14),

    /*
    |--------------------------------------------------------------------------
    | Storage Quota
    |--------------------------------------------------------------------------
    |
    | Optional per-install ceiling in bytes, shown on the repository page as a
    | usage bar. Null shows usage without enforcing a limit.
    |
    */

    'storage_quota_bytes' => env('DOCUMENTS_QUOTA'),

    /*
    |--------------------------------------------------------------------------
    | Allowed File Types
    |--------------------------------------------------------------------------
    |
    | Extension => mime types accepted for that extension. Anything not listed
    | is rejected, and the blocked list below is refused even if some future
    | edit adds it here by accident. SVG and HTML are blocked deliberately:
    | both execute script when opened in a browser tab.
    |
    */

    'allowed_extensions' => [
        'pdf' => ['application/pdf'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
        'gif' => ['image/gif'],
        'heic' => ['image/heic', 'image/heif'],
        'doc' => ['application/msword'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'xls' => ['application/vnd.ms-excel'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        'ppt' => ['application/vnd.ms-powerpoint'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation'],
        'txt' => ['text/plain'],
        'csv' => ['text/csv', 'text/plain', 'application/csv'],
        'dwg' => ['application/acad', 'image/vnd.dwg', 'application/octet-stream'],
        'dxf' => ['application/dxf', 'image/vnd.dxf', 'application/octet-stream'],
        'zip' => ['application/zip', 'application/x-zip-compressed'],
        'rar' => ['application/vnd.rar', 'application/x-rar-compressed'],
        '7z' => ['application/x-7z-compressed'],
        'mp4' => ['video/mp4'],
        'mov' => ['video/quicktime'],
    ],

    'blocked_extensions' => [
        'exe', 'bat', 'cmd', 'com', 'msi', 'sh', 'bash', 'zsh', 'ps1',
        'php', 'phar', 'phtml', 'js', 'mjs', 'jar', 'html', 'htm', 'svg',
        'dll', 'so', 'app', 'scr', 'vbs', 'hta',
    ],

];
