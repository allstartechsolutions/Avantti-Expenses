<?php

namespace App\Http\Controllers;

use App\Models\FileUpload;
use App\Services\FileUploadService;
use Illuminate\Support\Facades\Storage;

/**
 * Serves a documentation image that lives in cloud storage.
 *
 * The URL is permanent and the signature is not: a guide references the file
 * by its uuid, and every request is redirected to a freshly signed URL that
 * expires in minutes. Baking a signed URL into the markdown would give a guide
 * images that stop working after five minutes.
 */
class DocumentationFileController extends Controller
{
    public function __construct(private readonly FileUploadService $files)
    {
    }

    public function __invoke(string $uuid)
    {
        $file = FileUpload::where('uuid', $uuid)
            ->where('upload_status', FileUpload::STATUS_AVAILABLE)
            ->firstOrFail();

        // Only library assets are served here. A task's attachment goes
        // through its own screen, where the guards belong.
        abort_unless($file->attachable_type === null, 404);

        if ($url = $this->files->temporaryUrl($file, inline: true)) {
            return redirect()->away($url);
        }

        // No cloud storage on this install: stream it from the local disk.
        return response()->file(Storage::disk($file->disk)->path($file->object_key));
    }
}
