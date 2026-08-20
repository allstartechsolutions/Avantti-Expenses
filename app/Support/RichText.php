<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Making editor output safe to print.
 *
 * `strip_tags()` with an allowlist is not enough on its own: it keeps the
 * *attributes* of the tags it allows, so `<p onclick="...">` survives it
 * intact. Anything a person typed into an editor and anything a person will
 * later read has to go through here instead.
 *
 * Only tags TinyMCE's toolbar can actually produce are kept, every attribute
 * is dropped except a checked href, and javascript: URLs never make it out.
 */
class RichText
{
    private const ALLOWED_TAGS = [
        'p', 'br', 'div', 'span',
        'strong', 'b', 'em', 'i', 'u', 's', 'sub', 'sup',
        'ul', 'ol', 'li',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'blockquote', 'pre', 'code', 'hr',
        'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td',
        'a', 'img', 'figure', 'figcaption',
    ];

    /**
     * The only attributes kept, per tag. Everything else goes, which is what
     * stops `<img onerror=…>` and `<p onclick=…>` dead.
     */
    private const ALLOWED_ATTRIBUTES = [
        'a' => ['href'],
        'img' => ['src', 'alt', 'title'],
    ];

    /** Attributes holding a URL, which get checked rather than just kept. */
    private const URL_ATTRIBUTES = ['href', 'src'];

    private const SAFE_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    /**
     * A rendered guide: the same rules, plus the `id` on a heading, which is
     * what the contents list on the side jumps to.
     */
    public static function sanitizeDocument(?string $html): string
    {
        return self::sanitize($html, allowAnchors: true);
    }

    public static function sanitize(?string $html, bool $allowAnchors = false): string
    {
        if (blank($html)) {
            return '';
        }

        // Tags that carry code never reach the parser: their content is not
        // text somebody meant to read.
        $html = preg_replace('#<(script|style|iframe|object|embed|form)\b[^>]*>.*?</\1>#is', '', $html) ?? '';
        $html = preg_replace('#<(script|style|iframe|object|embed|form)\b[^>]*/?>#is', '', $html) ?? '';

        $document = new DOMDocument;

        $previous = libxml_use_internal_errors(true);

        // The meta forces UTF-8; without it DOMDocument reads the bytes as
        // Latin-1 and mangles every accented character.
        $document->loadHTML(
            '<?xml encoding="UTF-8"><body>'.$html.'</body>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $body = $document->getElementsByTagName('body')->item(0);

        if (! $body) {
            return '';
        }

        self::clean($body, $allowAnchors);

        $clean = '';

        foreach ($body->childNodes as $child) {
            $clean .= $document->saveHTML($child);
        }

        return trim($clean);
    }

    /** Is there anything at all once the markup is taken away? */
    public static function isEmpty(?string $html): bool
    {
        return trim(strip_tags((string) $html, '')) === ''
            && ! str_contains((string) $html, '<img');
    }

    /**
     * Walk depth-first, dropping tags that are not allowed while keeping what
     * they contained, and stripping every attribute that is not vouched for.
     */
    private static function clean(DOMNode $node, bool $allowAnchors = false): void
    {
        // Snapshot: the list is mutated as nodes are unwrapped.
        foreach (iterator_to_array($node->childNodes) as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            self::clean($child, $allowAnchors);

            $tag = strtolower($child->nodeName);

            if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                self::unwrap($child);

                continue;
            }

            foreach (iterator_to_array($child->attributes ?? []) as $attribute) {
                $name = strtolower($attribute->nodeName);
                $allowed = self::ALLOWED_ATTRIBUTES[$tag] ?? [];

                if ($allowAnchors && $name === 'id' && in_array($tag, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'], true)) {
                    $allowed[] = 'id';
                }

                // alt and title are text, not addresses: checking them as URLs
                // would throw away every caption.
                $isUrl = in_array($name, self::URL_ATTRIBUTES, true);

                if (! in_array($name, $allowed, true) || ($isUrl && ! self::isSafeUrl($attribute->nodeValue))) {
                    $child->removeAttribute($attribute->nodeName);
                }
            }

            // An image whose source was rejected is not an image.
            if ($tag === 'img' && ! $child->hasAttribute('src')) {
                $child->parentNode?->removeChild($child);

                continue;
            }

            // A link that leaves the app should not carry the referrer or the
            // opener window with it.
            if ($tag === 'a' && $child->hasAttribute('href')) {
                $child->setAttribute('target', '_blank');
                $child->setAttribute('rel', 'noopener noreferrer');
            }
        }
    }

    /** Replace an element with its children, keeping the text it held. */
    private static function unwrap(DOMElement $element): void
    {
        $parent = $element->parentNode;

        if (! $parent) {
            return;
        }

        while ($element->firstChild) {
            $parent->insertBefore($element->firstChild, $element);
        }

        $parent->removeChild($element);
    }

    private static function isSafeUrl(?string $url): bool
    {
        $url = trim((string) $url);

        if ($url === '') {
            return false;
        }

        // Relative links stay inside the app and are fine.
        if (str_starts_with($url, '/') || str_starts_with($url, '#')) {
            return true;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, self::SAFE_SCHEMES, true);
    }
}
