<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Works out where a visit came from.
 *
 * One touch is a moment a visitor arrived from somewhere identifiable: a
 * tagged link, a search result, a post on a social network, another site.
 * Moving around inside the shop is not a touch, which is the rule that stops
 * /product → /cart → /checkout from rewriting the source over and over.
 *
 * Everything this class returns has already been trimmed, length-capped and
 * stripped of anything that is not plainly a marketing value - it goes into a
 * cookie, then into the database, then onto an admin screen, and none of those
 * should have to filter it again.
 */
final class Attribution
{
    /** Nothing identifiable: a bare visit with no tag and no outside referrer. */
    public const DIRECT_SOURCE = 'direct';

    public const DIRECT_MEDIUM = 'direct';

    /**
     * The touch this request represents, or null when it is not one.
     *
     * Null is the common case and the important one: it means "leave whatever
     * was recorded alone". A visitor clicking through five pages produces one
     * touch on the way in and four nulls after it.
     *
     * @return array<string, string|null>|null
     */
    public static function detect(Request $request): ?array
    {
        $utm = self::utm($request);
        $referrer = self::referrer($request);
        $external = self::externalHost($request, $referrer);

        // A tagged link wins outright, whatever the referrer says. That is the
        // point of tagging one: the marketer has already named the source.
        if ($utm !== []) {
            return self::touch(
                $utm['source'] ?? $external ?? self::DIRECT_SOURCE,
                $utm['medium'] ?? ($external !== null ? self::mediumFor($external) : self::DIRECT_MEDIUM),
                $utm,
                $referrer,
                $request,
            );
        }

        if ($external !== null) {
            return self::touch(self::sourceFor($external), self::mediumFor($external), [], $referrer, $request);
        }

        // No tag and no outside referrer. That is a real touch only for someone
        // who has never been here before - the caller decides, because only it
        // knows whether anything was recorded already.
        return null;
    }

    /**
     * The touch to record for a visitor with no history and nothing to go on.
     *
     * @return array<string, string|null>
     */
    public static function direct(Request $request): array
    {
        return self::touch(self::DIRECT_SOURCE, self::DIRECT_MEDIUM, [], null, $request);
    }

    /**
     * @param  array<string, string>  $utm
     * @return array<string, string|null>
     */
    private static function touch(
        string $source,
        string $medium,
        array $utm,
        ?string $referrer,
        Request $request,
    ): array {
        return [
            'source' => $source,
            'medium' => $medium,
            'campaign' => $utm['campaign'] ?? null,
            'term' => $utm['term'] ?? null,
            'content' => $utm['content'] ?? null,
            'referrer' => $referrer,
            'landing_page' => self::landingPage($request),
            'at' => now()->toIso8601String(),
        ];
    }

    /* ----------------------------------------------------------------- utm */

    /**
     * The utm_* parameters on this request, sanitised. Empty when there are
     * none, which is what tells detect() to fall through to the referrer.
     *
     * @return array<string, string>
     */
    public static function utm(Request $request): array
    {
        $found = [];

        foreach (['source', 'medium', 'campaign', 'term', 'content'] as $key) {
            $value = self::clean($request->query('utm_'.$key));

            if ($value !== null) {
                $found[$key] = $value;
            }
        }

        // A campaign on its own, with no source, is not enough to name a touch
        // by - but it is still worth keeping against whatever the referrer
        // turns out to be, so the whole set is returned either way.
        return $found;
    }

    /* ------------------------------------------------------------ referrer */

    /**
     * The Referer header, capped and only if it parses as an http(s) URL.
     */
    public static function referrer(Request $request): ?string
    {
        $referrer = trim((string) $request->headers->get('referer'));

        if ($referrer === '') {
            return null;
        }

        $scheme = parse_url($referrer, PHP_URL_SCHEME);

        // Anything that is not a plain web address is a malformed or hostile
        // header rather than a link somebody followed.
        if (! in_array(strtolower((string) $scheme), ['http', 'https'], true)) {
            return null;
        }

        return mb_substr($referrer, 0, (int) config('shop.attribution.max_referrer_length', 500));
    }

    /**
     * The host a visitor came from, or null when they came from us.
     *
     * This is the internal-navigation guard: a referrer pointing at our own
     * host is somebody clicking a link on the site, not a new source.
     */
    public static function externalHost(Request $request, ?string $referrer): ?string
    {
        if ($referrer === null) {
            return null;
        }

        $host = self::normaliseHost(parse_url($referrer, PHP_URL_HOST));

        if ($host === null || $host === self::normaliseHost($request->getHost())) {
            return null;
        }

        // The configured address too, so a request arriving on a LAN IP or
        // behind a proxy still recognises its own domain.
        $configured = self::normaliseHost(parse_url((string) config('app.url'), PHP_URL_HOST));

        return $host === $configured ? null : $host;
    }

    /* ------------------------------------------------------------ matching */

    /**
     * What to record as the source for a referring host: the search engine or
     * network by name, otherwise the domain itself - which is exactly what a
     * backlink report wants to read.
     */
    public static function sourceFor(string $host): string
    {
        return self::match($host, config('shop.attribution.search_engines', []))
            ?? self::match($host, config('shop.attribution.social_networks', []))
            ?? self::clean($host)
            ?? self::DIRECT_SOURCE;
    }

    public static function mediumFor(string $host): string
    {
        if (self::match($host, config('shop.attribution.search_engines', [])) !== null) {
            return 'organic';
        }

        if (self::match($host, config('shop.attribution.social_networks', [])) !== null) {
            return 'social';
        }

        return 'referral';
    }

    /**
     * Matches a host against a configured list.
     *
     * Compared against the host's labels rather than with a substring search,
     * so "google.com" matches "www.google.co.uk" while "notgoogle.com" does
     * not - the difference between a real match and a lookalike domain.
     *
     * @param  array<string, string>  $needles
     */
    private static function match(string $host, array $needles): ?string
    {
        foreach ($needles as $needle => $name) {
            if ($host === $needle || str_ends_with($host, '.'.$needle)) {
                return $name;
            }

            // A bare key like "google" matches any label: google.com,
            // google.co.uk, news.google.de.
            if (! str_contains($needle, '.') && in_array($needle, explode('.', $host), true)) {
                return $name;
            }
        }

        return null;
    }

    /* ------------------------------------------------------------- values */

    /**
     * The page the visitor landed on, as a path and query. The host is left
     * off because it is always ours, and the fragment because it never reaches
     * the server.
     */
    public static function landingPage(Request $request): string
    {
        $path = '/'.ltrim($request->getPathInfo(), '/');
        $query = $request->getQueryString();

        return mb_substr(
            $query ? $path.'?'.$query : $path,
            0,
            (int) config('shop.attribution.max_landing_page_length', 255),
        );
    }

    /**
     * A marketing value, reduced to something safe to store and print.
     *
     * Lower-cased so "Facebook" and "facebook" are one row in a report, and
     * narrowed to the characters campaign names actually use - which also
     * removes the angle brackets and quotes that make a value dangerous
     * further down the line.
     */
    public static function clean(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = preg_replace('/[^a-z0-9._\- ]+/i', '', trim($value)) ?? '';
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');

        if ($value === '') {
            return null;
        }

        return mb_strtolower(mb_substr($value, 0, (int) config('shop.attribution.max_value_length', 100)));
    }

    private static function normaliseHost(mixed $host): ?string
    {
        if (! is_string($host) || $host === '') {
            return null;
        }

        // www is not a distinct site, and a trailing dot is a fully qualified
        // name for the same one.
        return preg_replace('/^www\./', '', rtrim(mb_strtolower($host), '.')) ?: null;
    }

    /**
     * How a stored value reads on an admin screen. Dashes rather than blanks,
     * so an empty cell is obviously empty rather than possibly broken.
     */
    public static function label(?string $value): string
    {
        return $value === null || $value === '' ? '—' : $value;
    }
}
