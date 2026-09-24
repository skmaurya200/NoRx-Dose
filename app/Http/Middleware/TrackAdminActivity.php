<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use App\Services\Audit\PresenceService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records every panel page a signed-in account opens, and keeps its session
 * marked as signed in. Runs on the /manager page routes after auth:admin, on
 * the way out, so only a page that actually rendered is counted.
 *
 * What is recorded is the page's name and path - never a query string, which
 * here is a sealed filter, and never anything from the request body.
 */
class TrackAdminActivity
{
    public function __construct(private readonly PresenceService $presence) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $admin = Auth::guard('admin')->user();

        if (! $admin instanceof Admin || ! $request->hasSession()) {
            return $response;
        }

        if ($request->isMethod('GET') && $response->isSuccessful() && ! $request->expectsJson()) {
            $this->presence->pageViewed($admin, $request, $this->pageName($request));
        } else {
            $this->presence->touch($admin, $request);
        }

        return $response;
    }

    /**
     * "manager.products.edit" with {product: 12} reads as "Products › Edit #12".
     */
    private function pageName(Request $request): string
    {
        $route = $request->route();
        $segments = array_values(array_diff(
            explode('.', Str::after((string) $route?->getName(), 'manager.')),
            ['index'],
        ));

        $name = collect($segments)
            ->map(fn (string $segment) => Str::headline($segment))
            ->implode(' › ') ?: 'Dashboard';

        $keys = collect($route?->parameters() ?? [])
            ->map(fn (mixed $value) => is_object($value) && method_exists($value, 'getKey') ? '#'.$value->getKey() : $value)
            ->filter(fn (mixed $value) => is_string($value) && strlen($value) <= 60)
            ->implode(' ');

        return trim($name.' '.$keys);
    }
}
