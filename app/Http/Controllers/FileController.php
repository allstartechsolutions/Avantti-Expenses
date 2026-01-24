<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileController extends Controller
{
    public function download(Request $request): StreamedResponse
    {
        $path = $request->query('path');

        if (!$path || !Storage::exists($path)) {
            abort(404, 'File not found');
        }

        $fileName = basename($path);
        $mimeType = Storage::mimeType($path);

        return Storage::download($path, $fileName, [
            'Content-Type' => $mimeType,
        ]);
    }

    public function show(Request $request)
    {
        $path = $request->query('path');

        if (!$path || !Storage::exists($path)) {
            abort(404, 'Image not found');
        }

        $mimeType = Storage::mimeType($path);

        return response()->stream(function () use ($path) {
            $stream = Storage::readStream($path);
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'public, max-age=31536000',
        ]);
    }
}
