<?php

namespace App\Http\Controllers;

use App\Models\SubcontractorDocument;
use App\Models\Vendor;
use App\Services\FileUploadService;
use Illuminate\Support\Facades\Storage;

/**
 * The one way out for a vendor document's file, whichever way it came in.
 *
 * A document filed since 2 Sep 2026 is a `file_uploads` row on the install's
 * document disk — a short-lived bucket URL on R2, a stream from the private
 * disk otherwise. A document filed before that is a path under
 * `subcontractor-documents/` on the private disk. Both answer to
 * `vendors.view` (the route's middleware) and to the vendor in the URL: an
 * id that belongs to another vendor is a 404, not a file.
 */
class SubcontractorDocumentController extends Controller
{
    // Bound as a plain vendor (no subcontractor scope): a supplier's documents
    // download through the same route (client request 27, Nov 2026).
    public function download(Vendor $subcontractor, SubcontractorDocument $document, FileUploadService $files)
    {
        abort_unless($document->subcontractor_id === $subcontractor->id, 404);

        if ($file = $document->fileUpload) {
            abort_unless($file->isAvailable(), 404);

            if ($url = $files->temporaryUrl($file)) {
                return redirect()->away($url);
            }

            return Storage::disk($file->disk)->download($file->object_key, $file->original_name);
        }

        abort_unless($document->file_path && Storage::disk('local')->exists($document->file_path), 404);

        return Storage::disk('local')->download($document->file_path, $document->file_name);
    }
}
