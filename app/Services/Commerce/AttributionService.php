<?php

namespace App\Services\Commerce;

use App\Models\Order;
use App\Models\OrderAttribution;
use App\Support\Attribution;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * Follows a visitor from wherever they arrived to the order they place.
 *
 * The shop takes guest orders - there are no customer accounts - so there is
 * nothing to key attribution to except the browser. One first-party cookie
 * carries it, and the order copies it at the moment it is written.
 *
 * The cookie is encrypted by Laravel's own EncryptCookies middleware, which is
 * why nothing here re-validates its shape beyond parsing it: a visitor cannot
 * edit it, so a payload that arrives is one this application wrote. It is
 * still read defensively, because an old payload from a previous version of
 * this code is a thing that happens.
 */
class AttributionService
{
    private const PAYLOAD_VERSION = 1;

    /**
     * Records this request against the visitor, if it is a touch worth
     * recording, and returns the payload as it now stands.
     *
     * Called on every storefront page view by the middleware. Most calls do
     * nothing at all - which is the point, because most page views are
     * somebody moving around inside the shop.
     *
     * @return array<string, mixed>
     */
    public function track(Request $request): array
    {
        $stored = $this->read($request);
        $touch = Attribution::detect($request);

        // Nothing identifiable about this request. For a visitor already being
        // followed that is internal navigation and must change nothing; for a
        // brand new one it is a direct arrival and is worth recording.
        if ($touch === null) {
            if ($stored !== null) {
                return $stored;
            }

            $touch = Attribution::direct($request);
        }

        $payload = [
            'v' => self::PAYLOAD_VERSION,
            'token' => $stored['token'] ?? (string) Str::uuid(),
            // Written once. A visitor's first touch is the answer to "how did
            // they find us", and it stops being that the moment it is rewritten.
            'first' => $stored['first'] ?? $touch,
            'last' => $touch,
        ];

        $this->write($payload);

        return $payload;
    }

    /* ---------------------------------------------------------------- order */

    /**
     * Copies the visitor's attribution onto an order.
     *
     * Read from the cookie rather than from anything the checkout form posted:
     * the browser is told what the source is, never asked.
     *
     * A visitor with no cookie - one who blocks them, or a request that
     * reached the checkout without ever loading a page - is recorded as
     * direct. That is honest: it is what the shop actually knows.
     */
    public function attachTo(Order $order, ?Request $request = null): OrderAttribution
    {
        $request ??= request();

        $payload = $this->read($request) ?? [
            'token' => null,
            'first' => Attribution::direct($request),
            'last' => Attribution::direct($request),
        ];

        $first = $payload['first'] ?? [];
        $last = $payload['last'] ?? $first;

        return $order->attribution()->create([
            'first_source' => $first['source'] ?? null,
            'first_medium' => $first['medium'] ?? null,
            'first_campaign' => $first['campaign'] ?? null,
            'first_term' => $first['term'] ?? null,
            'first_content' => $first['content'] ?? null,
            'first_referrer' => $first['referrer'] ?? null,
            'first_landing_page' => $first['landing_page'] ?? null,
            'first_at' => $first['at'] ?? null,

            'last_source' => $last['source'] ?? null,
            'last_medium' => $last['medium'] ?? null,
            'last_campaign' => $last['campaign'] ?? null,
            'last_term' => $last['term'] ?? null,
            'last_content' => $last['content'] ?? null,
            'last_referrer' => $last['referrer'] ?? null,
            'last_landing_page' => $last['landing_page'] ?? null,
            'last_at' => $last['at'] ?? null,

            'visitor_token' => $payload['token'] ?? null,
        ]);
    }

    /* -------------------------------------------------------------- reports */

    /**
     * Orders and revenue grouped by however the caller asks.
     *
     * $touch chooses which end of the journey is being credited: "last" for
     * what closed the sale, "first" for what started it. Both are stored, so
     * both are honest answers to different questions.
     *
     * Cancelled orders are left out throughout. They are not revenue, and
     * counting them would flatter whichever channel produced them.
     *
     * @param  array<int, string>  $columns  attribution columns to group by
     * @param  array<string, mixed>  $filters
     * @return Collection<int, object>
     */
    public function report(array $columns, string $touch = 'last', array $filters = [])
    {
        $prefixed = array_map(fn (string $column) => $touch.'_'.$column, $columns);

        $query = OrderAttribution::query()
            ->join('tbl_orders', 'tbl_orders.id', '=', 'tbl_order_attributions.order_id')
            ->whereNull('tbl_orders.deleted_at')
            ->where('tbl_orders.status', '!=', 'cancelled');

        if (! empty($filters['from'])) {
            $query->whereDate('tbl_orders.placed_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->whereDate('tbl_orders.placed_at', '<=', $filters['to']);
        }

        foreach ($prefixed as $column) {
            $query->groupBy('tbl_order_attributions.'.$column);
        }

        return $query
            ->selectRaw(implode(', ', array_map(
                fn (string $column) => 'tbl_order_attributions.'.$column,
                $prefixed,
            )))
            ->selectRaw('COUNT(*) as orders')
            ->selectRaw('COALESCE(SUM(tbl_orders.grand_total), 0) as revenue')
            ->orderByDesc('revenue')
            ->get()
            ->map(function ($row) use ($columns, $touch) {
                // Handed back on the plain names, so a view reading "source"
                // does not have to know which touch it asked for.
                foreach ($columns as $column) {
                    $row->{$column} = $row->{$touch.'_'.$column};
                }

                $row->revenue = (float) $row->revenue;
                $row->orders = (int) $row->orders;

                return $row;
            });
    }

    /* --------------------------------------------------------------- cookie */

    /**
     * The stored payload, or null when there is none to read.
     *
     * @return array<string, mixed>|null
     */
    public function read(Request $request): ?array
    {
        $raw = $request->cookie($this->cookieName());

        if (! is_string($raw) || $raw === '') {
            return null;
        }

        $payload = json_decode($raw, true);

        // Not JSON, so EncryptCookies did not run on this request and the raw
        // ciphertext is what arrived. That happens on the checkout POST, which
        // is an API route: whether the cookie is decrypted for it depends on
        // Sanctum's stateful-domain check, and attribution should not quietly
        // fall back to "direct" because of a hostname setting.
        if (! is_array($payload)) {
            $payload = $this->decrypt($raw);
        }

        // A payload written by an older version of this code, or one that did
        // not survive decryption at all, is discarded rather than half-used.
        // The visitor is then treated as new, which is the safe way to be wrong.
        if (! is_array($payload) || ! isset($payload['first']['source'])) {
            return null;
        }

        return $payload;
    }

    /**
     * Unwraps a cookie this application encrypted.
     *
     * Laravel prefixes an encrypted cookie with a hash of its name and the app
     * key, so a value cannot be lifted from one cookie into another. That
     * prefix is stripped here the same way the framework's own middleware
     * strips it.
     *
     * @return array<string, mixed>|null
     */
    private function decrypt(string $raw): ?array
    {
        try {
            $value = CookieValuePrefix::remove(
                Crypt::decrypt($raw, unserialize: false),
            );
        } catch (DecryptException) {
            // A cookie from a previous APP_KEY, or one somebody tampered with.
            return null;
        }

        $payload = json_decode($value, true);

        return is_array($payload) ? $payload : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function write(array $payload): void
    {
        // Queued rather than sent: the response has not been built yet, and
        // Laravel attaches queued cookies to whatever it turns out to be.
        Cookie::queue(Cookie::make(
            $this->cookieName(),
            json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            $this->lifetimeMinutes(),
            path: '/',
            // Null lets Laravel use the app's own host, so the cookie is
            // first-party wherever the shop is served from.
            domain: null,
            secure: $this->secure(),
            httpOnly: true,
            // Lax, not Strict: a visitor arriving from Google or Facebook is a
            // cross-site navigation, and Strict would withhold the cookie on
            // exactly the request that matters most.
            sameSite: 'lax',
        ));
    }

    /**
     * Forgets a visitor. Nothing calls this in the shop; it exists so a test,
     * or a privacy request, has a supported way to clear the cookie.
     */
    public function forget(): void
    {
        Cookie::queue(Cookie::forget($this->cookieName()));
    }

    private function cookieName(): string
    {
        return (string) config('shop.attribution.cookie', 'aurum_attr');
    }

    private function lifetimeMinutes(): int
    {
        return max(1, (int) config('shop.attribution.lifetime_days', 90)) * 24 * 60;
    }

    /**
     * HTTPS-only wherever the shop runs on HTTPS. Following the session's own
     * setting keeps one decision in one place.
     */
    private function secure(): bool
    {
        return (bool) config('session.secure', false);
    }
}
