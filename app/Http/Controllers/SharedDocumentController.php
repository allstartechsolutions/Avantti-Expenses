<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentActivity;
use App\Models\DocumentShare;
use App\Services\DocumentStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves files through a share link, to people with no login.
 *
 * Every request re-checks the link: not revoked, not expired, not past its
 * download limit, and unlocked if it carries a password. Nothing here trusts
 * that the landing page already checked — the URL can be used on its own.
 */
class SharedDocumentController extends Controller
{
    public function __construct(private readonly DocumentStorageService $storage)
    {
    }

    /**
     * The session key that remembers a visitor got the password right.
     */
    public static function unlockedKey(DocumentShare $share): string
    {
        return 'document_share_unlocked_'.$share->id;
    }

    public function download(Request $request, string $token, ?Document $document = null)
    {
        return $this->serve($request, $token, $document, inline: false);
    }

    /**
     * Open in the browser. This is the only way to read a link whose download
     * is switched off.
     */
    public function view(Request $request, string $token, ?Document $document = null)
    {
        return $this->serve($request, $token, $document, inline: true);
    }

    // =========================================================================
    // INTERNALS
    // =========================================================================

    private function serve(Request $request, string $token, ?Document $document, bool $inline)
    {
        $share = DocumentShare::where('token', $token)->first();

        abort_unless($share, 404);
        abort_unless($share->isUsable(), 410, 'This link is no longer available.');

        if ($share->requiresPassword() && ! $request->session()->get(self::unlockedKey($share))) {
            return redirect()->route('documents.share', $token);
        }

        if (! $inline && ! $share->allow_download) {
            abort(403, 'This link does not allow downloads.');
        }

        $document = $this->resolveDocument($share, $document);
        $version = $document->currentVersion;

        abort_if(! $version || ! $version->isAvailable(), 404, 'This document has no stored file.');

        // Only a real download spends the allowance; opening the preview does
        // not, or a link with one download left would be used up by looking.
        if (! $inline) {
            $share->increment('download_count');
            $share->forceFill(['last_accessed_at' => now()])->save();
        } else {
            $share->forceFill(['last_accessed_at' => now()])->save();
        }

        DocumentActivity::record(
            DocumentActivity::SHARE_ACCESSED,
            ['document_id' => $document->id, 'share_id' => $share->id],
            ['action' => $inline ? 'view' : 'download', 'version' => $version->version_number]
        );

        $name = $document->name ?: $version->original_name;

        if ($url = $this->storage->temporaryUrl($version, $name, $inline)) {
            return redirect()->away($url);
        }

        return $this->stream($version, $name, $inline);
    }

    /**
     * Which document this request is allowed to reach: the shared one, or —
     * for a folder link — one that really sits inside the shared folder.
     */
    private function resolveDocument(DocumentShare $share, ?Document $document): Document
    {
        if (! $share->isFolderShare()) {
            abort_unless($share->document, 404);

            // A file-link URL must not be pointed at some other document id.
            abort_if($document && $document->id !== $share->document_id, 403);

            return $share->document;
        }

        abort_unless($document, 404);

        $folder = $share->folder;

        abort_unless($folder, 404);
        abort_unless(in_array($document->folder_id, $folder->descendantIds(), true), 403);

        // Matches what the folder page lists: an internal document is not part
        // of a folder share, only of a link made for it deliberately.
        abort_if($document->is_internal, 403);

        return $document;
    }

    private function stream($version, string $downloadName, bool $inline): StreamedResponse
    {
        $disk = Storage::disk($version->disk);

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
            'X-Robots-Tag' => 'noindex, nofollow',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ]);
    }
}
