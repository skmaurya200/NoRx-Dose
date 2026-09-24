<?php

namespace App\Http\Middleware;

use App\Models\ActivityLog;
use App\Models\Admin;
use App\Services\Audit\ActivityLogService;
use App\Services\Audit\PresenceService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records every change an operator makes through the existing back-office
 * modules - products, orders, coupons, settings and the rest - without those
 * modules having to know the activity log exists.
 *
 * Only writes are recorded, only for a signed-in panel account, and only the
 * route, its model ids and the outcome. The request body is never read, so a
 * password, a card number or an uploaded file cannot end up in the log.
 *
 * Auth, user management and the activity log itself write their own, more
 * specific entries and are skipped here.
 */
class LogManagerActivity
{
    private const SKIP = ['api.manager.auth.*', 'api.manager.users.*', 'api.manager.activity-logs.*'];

    public function __construct(
        private readonly ActivityLogService $activity,
        private readonly PresenceService $presence,
    ) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // A panel page's own calls - a save, the inbox polling - keep the
        // browser counted as signed in. Session sign-ins only; a bearer token
        // is not somebody sitting in the panel.
        if ($request->routeIs('api.manager.*') && $request->hasSession() && Auth::guard('admin')->check()) {
            $this->presence->touch(Auth::guard('admin')->user(), $request);
        }

        if ($request->isMethodSafe() || ! $request->routeIs('api.manager.*') || $request->routeIs(self::SKIP)) {
            return $response;
        }

        $admin = $request->user();

        if (! $admin instanceof Admin) {
            return $response;
        }

        $routeName = (string) $request->route()?->getName();
        $action = Str::upper(str_replace(['.', '-'], '_', Str::after($routeName, 'api.manager.')));
        $succeeded = $response->getStatusCode() < 400;

        $this->activity->record(
            Str::limit($action, 60, ''),
            sprintf('%s %s (%s).', $succeeded ? 'Performed' : 'Attempted', Str::of($action)->replace('_', ' ')->lower(), $request->method()),
            $admin,
            $succeeded ? ActivityLog::STATUS_SUCCESS : ActivityLog::STATUS_FAILED,
            $this->routeKeys($request) + ['http_status' => $response->getStatusCode()],
        );

        return $response;
    }

    /**
     * The ids in the URL - which product, which order - and nothing else.
     *
     * @return array<string, int|string>
     */
    private function routeKeys(Request $request): array
    {
        return collect($request->route()?->parameters() ?? [])
            ->map(fn (mixed $value) => is_object($value) && method_exists($value, 'getKey') ? $value->getKey() : $value)
            ->filter(fn (mixed $value) => is_int($value) || (is_string($value) && strlen($value) <= 60))
            ->mapWithKeys(fn (mixed $value, string $key) => ["{$key}_id" => $value])
            ->all();
    }
}
