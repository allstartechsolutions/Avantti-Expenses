<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentActivity;
use App\Models\DocumentVersion;
use App\Services\DocumentStorageService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves repository documents.
 *
 * Files are addressed by record id and authorized against that record — a
 * storage path is never accepted from the browser. On Cloudflare R2 the user
 * is redirected to a presigned URL that expires in minutes; on the local disk
 * the file is streamed.
 */
class DocumentFileController extends Controller
{
    public function __construct(private readonly DocumentStorageService $storage)
    {
    }

    /**
     * Download the current version.
     */
    public function download(Document $document)
    {
        return $this->serve($document, $document->currentVersion, inline: false);
    }

    /**
     * Show it in the browser: PDFs, images and video.
     */
    public function preview(Document $document)
    {
        return $this->serve($document, $document->currentVersion, inline: true);
    }

    /**
     * Download one specific version from the history.
     */
    public function downloadVersion(Document $document, DocumentVersion $version)
    {
        abort_unless($version->document_id === $document->id, 404);

        return $this->serve($document, $version, inline: false);
    }

    // =========================================================================
    // INTERNALS
    // =========================================================================

    private function serve(Document $document, ?DocumentVersion $version, bool $inline)
    {
        abort_unless($document->isVisibleTo(auth()->user()), 403, 'You may not open this document.');
        abort_if(! $version || ! $version->isAvailable(), 404, 'This document has no stored file.');

        $downloadName = $this->downloadName($document, $version);

        DocumentActivity::record(
            $inline ? DocumentActivity::PREVIEWED : DocumentActivity::DOWNLOADED,
            ['document_id' => $document->id],
            ['version' => $version->version_number]
        );

        if ($url = $this->storage->temporaryUrl($version, $downloadName, $inline)) {
            return redirect()->away($url);
        }

        return $this->stream($version, $downloadName, $inline);
    }

    private function stream(DocumentVersion $version, string $downloadName, bool $inline): StreamedResponse
    {
        $disk = \Illuminate\Support\Facades\Storage::disk($version->disk);

        abort_unless($disk->exists($version->object_key), 404, 'File not found');

        return response()->stream(function () use ($disk, $version) {
            $stream = $disk->readStream($version->object_key);
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type' => $version->mime_type ?: 'application/octet-stream',
            'Content-Length' => $version->size_bytes,
            'Content-Disposition' => sprintf(
                '%s; filename="%s"',
                $inline ? 'inline' : 'attachment',
                str_replace('"', '', $downloadName)
            ),
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }

    /**
     * What the file is called when it lands in the user's downloads: the
     * document's name, with the version number when it is not the current one.
     */
    private function downloadName(Document $document, DocumentVersion $version): string
    {
        $name = $document->name ?: $version->original_name;

        if ($version->id === $document->current_version_id) {
            return $name;
        }

        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $base = pathinfo($name, PATHINFO_FILENAME);

        return $extension
            ? "{$base} (v{$version->version_number}).{$extension}"
            : "{$base} (v{$version->version_number})";
    }
}
