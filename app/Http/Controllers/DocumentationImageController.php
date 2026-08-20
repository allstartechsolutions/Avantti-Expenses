<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves the images a shipped guide references.
 *
 * The guides live in docs/, which is not under the web root and must not be:
 * the same directory holds the plans, the review backlog and every changelog.
 * So the path is checked against a short allowlist of roots and extensions,
 * and resolved with realpath() before anything is read — a guide can never be
 * used to walk out of docs/images and read the rest of the repository.
 */
class DocumentationImageController extends Controller
{
    public function __invoke(Request $request, string $path): BinaryFileResponse
    {
        $roots = collect(config('documentation.image_roots', []))
            ->map(fn (string $root) => realpath(base_path($root)))
            ->filter()
            ->all();

        abort_if(empty($roots), 404);

        $resolved = realpath(base_path($path));

        // Not there, or not inside one of the roots — the same 404 either way,
        // so probing tells an attacker nothing.
        abort_unless($resolved && is_file($resolved), 404);

        $inside = collect($roots)->contains(fn (string $root) => str_starts_with($resolved, $root.DIRECTORY_SEPARATOR));

        abort_unless($inside, 404);

        $extension = strtolower(pathinfo($resolved, PATHINFO_EXTENSION));

        abort_unless(in_array($extension, config('documentation.image_extensions', []), true), 404);

        return response()
            ->file($resolved, ['Cache-Control' => 'private, max-age=3600'])
            ->setPrivate();
    }
}
