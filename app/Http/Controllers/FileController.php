<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves the files stored against records by the older modules — expense
 * receipts, purchase order receipts, contract files, change order files,
 * daily report images, subcontractor documents and polymorphic attachments.
 *
 * The path arrives in the query string, so it is treated as hostile: it must
 * be relative, free of traversal segments, and live under one of the
 * directories this application actually writes to. Without those checks a
 * signed-in user could walk out of the storage root and read .env or the
 * database file.
 *
 * New work does not use this controller. The document repository addresses
 * files by record id (see DocumentFileController) and never exposes a path.
 */
class FileController extends Controller
{
    /**
     * Directories the application stores files in on the private disk.
     * A path outside every one of these is refused.
     */
    private const ALLOWED_DIRECTORIES = [
        'expenses',
        'income',
        'purchase-orders',
        'requisitions',
        'quotations',
        'contracts',
        'contract-change-orders',
        'change_orders',
        'daily_reports',
        'temp_daily_reports',
        'subcontractor-documents',
        'company-logos',
        'livewire-tmp',
    ];

    public function download(Request $request): StreamedResponse
    {
        $path = $this->resolvePath($request);

        return Storage::download($path, basename($path), [
            'Content-Type' => Storage::mimeType($path),
        ]);
    }

    public function show(Request $request)
    {
        $path = $this->resolvePath($request);

        return response()->stream(function () use ($path) {
            $stream = Storage::readStream($path);
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type' => Storage::mimeType($path),
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    /**
     * Validate the requested path and confirm the file exists, or abort.
     */
    private function resolvePath(Request $request): string
    {
        $path = (string) $request->query('path', '');

        abort_if($path === '' || ! $this->isSafePath($path), 404, 'File not found');
        abort_unless(Storage::exists($path), 404, 'File not found');

        return $path;
    }

    /**
     * A path is safe when it is relative, contains no traversal or null byte,
     * and sits inside one of the directories the application writes to.
     */
    private function isSafePath(string $path): bool
    {
        if (str_contains($path, "\0") || str_contains($path, '\\')) {
            return false;
        }

        if (str_starts_with($path, '/') || str_starts_with($path, '.')) {
            return false;
        }

        $segments = explode('/', $path);

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return false;
            }
        }

        return in_array($segments[0], self::ALLOWED_DIRECTORIES, true);
    }
}
