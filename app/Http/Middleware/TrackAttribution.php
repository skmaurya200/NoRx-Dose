<?php

namespace App\Http\Middleware;

use App\Services\Commerce\AttributionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records where a storefront visitor came from.
 *
 * Runs on every storefront page view, which sounds like a lot until you note
 * what it usually does: nothing. A request with no tag and no outside referrer
 * from a visitor already being followed is internal navigation, and the
 * service leaves the cookie exactly as it was.
 *
 * Deliberately narrow about what it looks at:
 *
 *  - GET only. A form post is not an arrival, and the checkout POST must never
 *    be able to disturb attribution that a page view established.
 *  - Page views only. An image or a stylesheet is not a visit.
 *  - Storefront only. Nobody arrives at the admin panel from a campaign.
 */
class TrackAttribution
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->shouldTrack($request)) {
            // Never at the cost of a page. Attribution is a marketing nicety;
            // a shop that will not load is not.
            rescue(fn () => app(AttributionService::class)->track($request), report: true);
        }

        return $next($request);
    }

    private function shouldTrack(Request $request): bool
    {
        if (! $request->isMethod('GET') || $request->ajax()) {
            return false;
        }

        // The panel, the API and the crawler files are not visits by a shopper.
        if ($request->is('manager', 'manager/*', 'api/*', 'sitemap.xml', 'robots.txt', 'up')) {
            return false;
        }

        // A browser asking for a page, rather than for an asset it will embed.
        return $request->acceptsHtml();
    }
}
