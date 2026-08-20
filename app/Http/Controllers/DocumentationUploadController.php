<?php

namespace App\Http\Controllers;

use App\Models\DocArticle;
use App\Services\DocumentSettings;
use App\Services\FileUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * The image button in the guide editor.
 *
 * Unlike the repository and task uploads, the bytes come through PHP here:
 * the editor posts the blob itself, and a screenshot in a guide is measured in
 * megabytes, not gigabytes. Storage is the same bucket as everything else.
 */
class DocumentationUploadController extends Controller
{
    public function __construct(private readonly FileUploadService $files)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        abort_unless(
            auth()->user()?->is_admin || auth()->user()?->is_manager,
            403,
            'Manager or administrator access required.'
        );

        $data = $request->validate([
            'file' => ['required', 'file', 'image', 'max:10240'],
            'article_id' => ['nullable', 'integer', 'exists:doc_articles,id'],
        ], [
            'file.image' => __('Only images can be added to a guide this way.'),
            'file.max' => __('Images in a guide are limited to 10 MB.'),
        ]);

        $upload = $data['file'];

        if (! DocumentSettings::isAllowedFile($upload->getClientOriginalName(), $upload->getMimeType())) {
            return response()->json(['message' => __('This file type is not allowed.')], 422);
        }

        // A guide being written for the first time has no id yet, so its
        // images belong to the library until it is saved; either way they live
        // under the documentation prefix and are served by the same route.
        $article = isset($data['article_id']) ? DocArticle::find($data['article_id']) : null;

        $key = sprintf(
            '%s/%s/%s/%s',
            FileUploadService::LIBRARY_PREFIX,
            $article ? 'articles/'.$article->id : 'uploads',
            (string) Str::uuid(),
            DocumentSettings::sanitizeFileName($upload->getClientOriginalName())
        );

        $file = $this->files->storeLibraryFile($upload->getRealPath(), $key, auth()->id());

        $file->update([
            'original_name' => DocumentSettings::sanitizeFileName($upload->getClientOriginalName()),
            'mime_type' => $upload->getMimeType(),
        ]);

        // The shape TinyMCE expects back.
        return response()->json(['location' => route('documentation.image', $file->uuid)]);
    }
}
