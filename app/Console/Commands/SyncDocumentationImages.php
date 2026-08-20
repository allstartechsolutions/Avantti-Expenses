<?php

namespace App\Console\Commands;

use App\Services\DocumentSettings;
use App\Services\FileUploadService;
use Illuminate\Console\Command;
use Symfony\Component\Finder\Finder;

/**
 * Push the images the shipped guides reference into cloud storage.
 *
 * The markdown keeps relative paths, so a guide stays portable across
 * installs; this command is what gives one install its own copy in its own
 * bucket. Run it on deploy, or after adding a guide.
 *
 * Files already stored with the same checksum are skipped, so running it twice
 * costs nothing.
 */
class SyncDocumentationImages extends Command
{
    protected $signature = 'documentation:sync-images {--force : Re-upload even when the stored copy matches}';

    protected $description = 'Upload the images used by the shipped documentation guides to cloud storage';

    public function handle(FileUploadService $files): int
    {
        if (! DocumentSettings::isCloudConfigured()) {
            $this->warn('Cloud storage is not configured, so guides will keep serving their images from disk.');
            $this->line('Set the R2 credentials and DOCUMENTS_DISK, then run this again.');

            return self::SUCCESS;
        }

        $roots = collect(config('documentation.image_roots', []))
            ->map(fn (string $root) => base_path($root))
            ->filter(fn (string $root) => is_dir($root));

        if ($roots->isEmpty()) {
            $this->warn('No image directories configured.');

            return self::SUCCESS;
        }

        $extensions = config('documentation.image_extensions', []);
        $uploaded = $skipped = 0;

        foreach ($roots as $root) {
            $finder = (new Finder)->files()->in($root)->sortByName();

            foreach ($finder as $file) {
                if (! in_array(strtolower($file->getExtension()), $extensions, true)) {
                    continue;
                }

                // The key mirrors the path a guide writes, so rendering can
                // find it without a lookup table.
                $relative = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getRealPath());
                $key = FileUploadService::LIBRARY_PREFIX.'/'.str_replace(DIRECTORY_SEPARATOR, '/', $relative);

                $existing = $files->libraryFile($key);

                if ($existing && ! $this->option('force') && $existing->checksum === md5_file($file->getRealPath())) {
                    $skipped++;

                    continue;
                }

                $files->storeLibraryFile($file->getRealPath(), $key);

                $this->line('  uploaded '.$relative);
                $uploaded++;
            }
        }

        $this->info(sprintf('%d image(s) uploaded, %d already current.', $uploaded, $skipped));

        return self::SUCCESS;
    }
}
