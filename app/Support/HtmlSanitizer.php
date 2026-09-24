<?php

namespace App\Support;

use DOMAttr;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

/**
 * Cleans the HTML the panel's editor produces before it is stored.
 *
 * A post body is the one place in this application where markup written by a
 * person is printed unescaped, so it is filtered against an allow-list: a tag
 * that is not named here is unwrapped, and an attribute that is not named here
 * is dropped. Anything novel therefore fails closed.
 *
 * Sanitising on the way in rather than on the way out means the column only
 * ever holds markup the storefront is willing to print, and a second template
 * that forgets to filter cannot reintroduce the problem.
 *
 * This is not a defence against a hostile administrator - someone who can edit
 * posts can already do a great deal - but it does contain a pasted payload,
 * which is how this goes wrong in practice: an author copies formatted text
 * from a web page and brings a tracking script along with it.
 */
final class HtmlSanitizer
{
    /**
     * Tag => the attributes it may keep. Everything else is unwrapped, with
     * its text preserved.
     *
     * @var array<string, array<int, string>>
     */
    private const ALLOWED = [
        'p' => [],
        'br' => [],
        'strong' => [],
        'b' => [],
        'em' => [],
        'i' => [],
        'u' => [],
        's' => [],
        'blockquote' => [],
        // h1 through h6. The editor's block picker offers all six; the post
        // template already prints the title as the page's h1, so a second one
        // in the body is the author's call rather than the sanitiser's.
        'h1' => [],
        'h2' => [],
        'h3' => [],
        'h4' => [],
        'h5' => [],
        'h6' => [],
        'ul' => [],
        'ol' => [],
        'li' => [],
        'a' => ['href', 'title', 'target', 'rel'],
        'img' => ['src', 'alt', 'title', 'width', 'height'],
        'figure' => [],
        'figcaption' => [],
        'hr' => [],
        'code' => [],
        'pre' => [],
        'span' => [],
        'sub' => [],
        'sup' => [],
    ];

    /**
     * Elements removed outright, contents and all. Unwrapping a <script> would
     * leave its source printed as text, which is not what anyone wants either.
     *
     * @var array<int, string>
     */
    private const STRIPPED = [
        'script', 'style', 'iframe', 'object', 'embed', 'form', 'input',
        'button', 'select', 'textarea', 'link', 'meta', 'base', 'svg', 'math',
    ];

    /**
     * URL schemes a link or an image may use. Notably absent: javascript: and
     * data:, the two that turn an href into code.
     *
     * @var array<int, string>
     */
    private const SCHEMES = ['http', 'https', 'mailto', 'tel'];

    /**
     * Tags a heading may carry. Far narrower than ALLOWED, because a heading
     * is a design element: the italic word in "Modern science. Bespoke
     * wellness." has to stay editable without opening the page's largest
     * piece of type to arbitrary markup.
     *
     * @var array<int, string>
     */
    private const INLINE = ['em', 'i', 'strong', 'b', 'br', 'span', 'sub', 'sup'];

    /**
     * Cleans a heading or a short line of copy, keeping only inline emphasis.
     * Everything else is unwrapped, so the words survive and the markup does
     * not - a pasted <div> becomes text, a <script> disappears entirely.
     */
    public static function inline(?string $html): string
    {
        $cleaned = self::clean($html);

        if ($cleaned === '') {
            return '';
        }

        $keep = implode('', array_map(fn (string $tag) => '<'.$tag.'>', self::INLINE));

        // strip_tags rather than a second DOM pass: the input has already been
        // through the allow-list above, so all that is left is to narrow it.
        return trim(strip_tags($cleaned, $keep));
    }

    public static function clean(?string $html): string
    {
        $html = trim((string) $html);

        if ($html === '') {
            return '';
        }

        $document = new DOMDocument;

        // The editor sends a fragment. Wrapping it keeps DOMDocument from
        // inventing <html><body>, and the meta forces UTF-8 - without it
        // loadHTML assumes Latin-1 and mangles every accented character.
        $previous = libxml_use_internal_errors(true);

        $document->loadHTML(
            '<?xml encoding="UTF-8"><div id="__root">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById('__root');

        if ($root === null) {
            return '';
        }

        self::stripForbidden($document);
        self::walk($root);

        $out = '';

        foreach ($root->childNodes as $child) {
            $out .= $document->saveHTML($child);
        }

        return trim($out);
    }

    /**
     * A plain-text rendering, for excerpts and the reading-time estimate.
     */
    public static function toText(?string $html): string
    {
        $text = strip_tags(str_replace(['</p>', '<br>', '<br/>', '<br />', '</li>'], ' ', (string) $html));

        return trim(preg_replace('/\s+/u', ' ', html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
    }

    /**
     * Whether the body is empty once the markup is taken away. An editor left
     * untouched still posts "<p><br></p>", which is not content.
     */
    public static function isBlank(?string $html): bool
    {
        return self::toText($html) === '' && ! str_contains((string) $html, '<img');
    }

    /* -------------------------------------------------------------- private */

    private static function stripForbidden(DOMDocument $document): void
    {
        $xpath = new DOMXPath($document);

        foreach (self::STRIPPED as $tag) {
            // Iterated into an array first: removing nodes while walking a
            // live DOMNodeList skips every other match.
            $nodes = iterator_to_array($xpath->query('//'.$tag) ?: []);

            foreach ($nodes as $node) {
                $node->parentNode?->removeChild($node);
            }
        }

        // Comments can carry conditional-comment payloads and are never
        // meaningful in a post body.
        foreach (iterator_to_array($xpath->query('//comment()') ?: []) as $comment) {
            $comment->parentNode?->removeChild($comment);
        }
    }

    private static function walk(DOMNode $node): void
    {
        // A copy, because the loop rewrites the child list as it goes.
        foreach (iterator_to_array($node->childNodes) as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            self::walk($child);

            $tag = strtolower($child->nodeName);

            if (! array_key_exists($tag, self::ALLOWED)) {
                self::unwrap($child);

                continue;
            }

            self::filterAttributes($child, self::ALLOWED[$tag]);
        }
    }

    /**
     * Replaces an element with its own children, so an unknown wrapper loses
     * its markup without losing the words inside it.
     */
    private static function unwrap(DOMElement $element): void
    {
        $parent = $element->parentNode;

        if ($parent === null) {
            return;
        }

        while ($element->firstChild !== null) {
            $parent->insertBefore($element->firstChild, $element);
        }

        $parent->removeChild($element);
    }

    /**
     * @param  array<int, string>  $allowed
     */
    private static function filterAttributes(DOMElement $element, array $allowed): void
    {
        foreach (iterator_to_array($element->attributes) as $attribute) {
            /** @var DOMAttr $attribute */
            $name = strtolower($attribute->nodeName);

            if (! in_array($name, $allowed, true)) {
                $element->removeAttribute($attribute->nodeName);

                continue;
            }

            if (($name === 'href' || $name === 'src') && ! self::safeUrl($attribute->nodeValue)) {
                $element->removeAttribute($attribute->nodeName);
            }
        }

        // A link that opens a new tab hands the opener a window reference
        // unless it says otherwise, so the pairing is enforced rather than
        // left to whoever wrote the post.
        if (strtolower($element->nodeName) === 'a' && $element->getAttribute('target') === '_blank') {
            $element->setAttribute('rel', 'noopener noreferrer');
        }
    }

    private static function safeUrl(?string $url): bool
    {
        $url = trim((string) $url);

        if ($url === '') {
            return false;
        }

        // Relative and root-relative URLs carry no scheme and are fine.
        if (str_starts_with($url, '/') || str_starts_with($url, '#')) {
            return true;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);

        if ($scheme === null || $scheme === false) {
            // No scheme and not absolute: a bare path like "about/team".
            // Rejected if it smells like an obfuscated scheme instead.
            return ! str_contains($url, ':');
        }

        return in_array(strtolower($scheme), self::SCHEMES, true);
    }
}
