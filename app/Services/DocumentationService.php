<?php

namespace App\Services;

use App\Models\DocArticle;
use App\Support\RichText;
use App\Services\FileUploadService;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The documentation library: guides shipped with the product and guides this
 * company wrote, presented as one thing.
 *
 * Shipped guides are markdown files under docs/ (registered in
 * config/documentation.php), so they are versioned with the code they describe
 * and can never drift from a release. Custom guides are rows an admin wrote in
 * the editor. A reader should not have to know or care which is which.
 */
class DocumentationService
{
    /**
     * Every guide a reader may open, both kinds, ordered.
     *
     * @return Collection<int, array>
     */
    public function library(bool $includeDrafts = false): Collection
    {
        return $this->shipped()
            ->concat($this->custom($includeDrafts))
            ->sortBy([['category_order', 'asc'], ['order', 'asc'], ['title', 'asc']])
            ->values();
    }

    /** @return Collection<int, array> */
    public function shipped(): Collection
    {
        return collect(config('documentation.guides', []))
            ->filter(fn (array $guide) => is_file(base_path($guide['file'])))
            ->map(fn (array $guide, string $slug) => [
                'slug' => $slug,
                'title' => $guide['title'],
                'summary' => $guide['summary'] ?? null,
                'category' => $guide['category'] ?? 'general',
                'category_label' => $this->categoryLabel($guide['category'] ?? 'general'),
                'category_order' => $this->categoryOrder($guide['category'] ?? 'general'),
                'order' => $guide['order'] ?? 100,
                'source' => 'shipped',
                'is_published' => true,
                'updated_at' => \Illuminate\Support\Carbon::createFromTimestamp(filemtime(base_path($guide['file']))),
                'file' => $guide['file'],
                'model' => null,
            ])
            ->values();
    }

    /** @return Collection<int, array> */
    public function custom(bool $includeDrafts = false): Collection
    {
        return DocArticle::query()
            ->unless($includeDrafts, fn ($q) => $q->published())
            ->with(['updatedBy', 'createdBy'])
            ->orderBy('position')
            ->get()
            ->map(fn (DocArticle $article) => [
                'slug' => $article->slug,
                'title' => $article->title,
                'summary' => $article->summary,
                'category' => $article->category,
                'category_label' => $this->categoryLabel($article->category),
                'category_order' => $this->categoryOrder($article->category),
                'order' => $article->position,
                'source' => 'custom',
                'is_published' => $article->is_published,
                'updated_at' => $article->updated_at,
                'file' => null,
                'model' => $article,
            ]);
    }

    /**
     * One guide, whichever kind it is, with its body rendered and safe to
     * print. Null when the slug is unknown or the reader may not see it.
     */
    public function find(string $slug, bool $includeDrafts = false): ?array
    {
        $entry = $this->library($includeDrafts)->firstWhere('slug', $slug);

        if (! $entry) {
            return null;
        }

        $entry['html'] = $entry['source'] === 'shipped'
            ? $this->renderMarkdown((string) file_get_contents(base_path($entry['file'])))
            : $entry['model']->safeBody();

        $entry['headings'] = $this->headings($entry['html']);

        return $entry;
    }

    /**
     * Search titles, summaries and body text.
     *
     * Plain substring matching on purpose: the library is tens of documents,
     * not thousands, and an index would be a moving part with nothing to gain.
     */
    public function search(string $term, bool $includeDrafts = false): Collection
    {
        $term = trim($term);

        if ($term === '') {
            return $this->library($includeDrafts);
        }

        return $this->library($includeDrafts)->filter(function (array $entry) use ($term) {
            $haystack = $entry['title'].' '.$entry['summary'].' '.$entry['category_label'].' '.$this->plainText($entry);

            return Str::contains($haystack, $term, ignoreCase: true);
        })->values();
    }

    /** Guides grouped under their category, in category order. */
    public function byCategory(bool $includeDrafts = false, ?string $term = null): Collection
    {
        $entries = $term !== null ? $this->search($term, $includeDrafts) : $this->library($includeDrafts);

        return $entries->groupBy('category')->map(fn (Collection $group, string $category) => [
            'label' => $this->categoryLabel($category),
            'icon' => config("documentation.categories.{$category}.icon", 'document'),
            'entries' => $group->values(),
        ])->sortBy(fn (array $group, string $category) => $this->categoryOrder($category));
    }

    // =========================================================================
    // RENDERING
    // =========================================================================

    /**
     * Markdown to HTML, with two adjustments.
     *
     * Images are rewritten to the serving route, because nothing under docs/
     * is reachable over the web; and the result goes through the same
     * sanitiser as everything else, so a guide cannot introduce script.
     */
    public function renderMarkdown(string $markdown): string
    {
        $html = Str::markdown($markdown);

        $html = preg_replace_callback(
            '/(<img[^>]+src=")([^"]+)(")/i',
            function (array $match) {
                $src = $match[2];

                // Absolute URLs and already-routed paths are left alone.
                if (Str::startsWith($src, ['http://', 'https://', '/', 'data:'])) {
                    return $match[1].$src.$match[3];
                }

                $path = 'docs/'.ltrim($src, './');

                // Prefer the copy in cloud storage; fall back to the file on
                // disk so a guide still renders before the images are synced,
                // and on an install with no cloud storage at all.
                $stored = app(FileUploadService::class)
                    ->libraryFile(FileUploadService::LIBRARY_PREFIX.'/'.$path);

                $url = $stored
                    ? route('documentation.image', $stored->uuid)
                    : route('documentation.asset', ['path' => $path]);

                return $match[1].$url.$match[3];
            },
            $html
        ) ?? $html;

        // Anchors, so the contents list on the side can jump.
        $html = preg_replace_callback(
            '/<h([2-3])>(.*?)<\/h\1>/s',
            fn (array $m) => sprintf('<h%d id="%s">%s</h%d>', $m[1], Str::slug(strip_tags($m[2])), $m[2], $m[1]),
            $html
        ) ?? $html;

        $html = RichText::sanitizeDocument($html);

        // A wide table scrolls inside its own box rather than pushing the page
        // sideways on a phone.
        return str_replace(
            ['<table>', '</table>'],
            ['<div class="table-scroll"><table>', '</table></div>'],
            $html
        );
    }

    /**
     * The contents list: the second and third level headings, in order.
     *
     * @return array<int, array{level:int, text:string, anchor:string}>
     */
    public function headings(string $html): array
    {
        preg_match_all('/<h([2-3])[^>]*>(.*?)<\/h\1>/s', $html, $matches, PREG_SET_ORDER);

        return array_map(fn (array $m) => [
            'level' => (int) $m[1],
            'text' => trim(strip_tags($m[2])),
            'anchor' => Str::slug(strip_tags($m[2])),
        ], $matches);
    }

    // =========================================================================
    // INTERNALS
    // =========================================================================

    private function plainText(array $entry): string
    {
        if ($entry['source'] === 'custom') {
            return strip_tags((string) $entry['model']->body);
        }

        $path = base_path($entry['file']);

        return is_file($path) ? (string) file_get_contents($path) : '';
    }

    private function categoryLabel(string $category): string
    {
        return __(config("documentation.categories.{$category}.label", Str::headline($category)));
    }

    private function categoryOrder(string $category): int
    {
        return (int) config("documentation.categories.{$category}.order", 500);
    }
}
