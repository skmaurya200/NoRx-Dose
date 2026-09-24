<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use App\Services\Auth\PanelSessionService;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Closes the gap that auth:sanctum alone leaves open. Sanctum will happily
 * authenticate a storefront customer (the web guard is in sanctum.guard), so
 * without this a logged-in customer could call the admin API. Run it after
 * auth:sanctum on every /api/manager route.
 *
 * It also re-checks is_active on every request, so disabling an account takes
 * effect immediately instead of when its token happens to expire.
 */
class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof Admin) {
            return ApiResponse::error(
                'This endpoint is restricted to administrator accounts.',
                Response::HTTP_FORBIDDEN,
            );
        }

        if (! $user->is_active) {
            $this->revoke($user);

            return ApiResponse::error(
                'This account has been disabled.',
                Response::HTTP_FORBIDDEN,
            );
        }

        if ($user->isLocked()) {
            $this->revoke($user);

            return ApiResponse::error(
                'This account is temporarily locked.',
                Response::HTTP_LOCKED,
            );
        }

        // A browser session an Admin signed out everywhere. The panel's own
        // calls get a 401, which its script already reads as "signed out".
        $panelSessions = app(PanelSessionService::class);

        if ($request->hasSession() && Auth::guard('admin')->check() && $panelSessions->isRevoked($user, $request)) {
            $panelSessions->endRevoked($request);

            return ApiResponse::error(
                'You have been signed out. Please sign in again.',
                Response::HTTP_UNAUTHORIZED,
            );
        }

        return $next($request);
    }

    /**
     * Drop the credential that got them here so a disabled or locked account
     * cannot keep replaying a still-valid token - or, for the browser panel,
     * a still-valid session.
     */
    private function revoke(Admin $admin): void
    {
        $token = $admin->currentAccessToken();

        if ($token !== null && method_exists($token, 'delete')) {
            $token->delete();

            return;
        }

        if (request()->hasSession() && Auth::guard('admin')->check()) {
            Auth::guard('admin')->logout();
            request()->session()->invalidate();
            request()->session()->regenerateToken();
        }
    }
}
