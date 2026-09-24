<?php

namespace App\Http\Middleware;

use App\Models\Admin;
use App\Services\Auth\PanelSessionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * The panel's pages equivalent of the checks EnsureAdmin makes on the API: a
 * browser session belonging to an account that has since been deactivated,
 * or whose sessions an Admin has signed out everywhere, is ended on its next
 * page load instead of browsing on until the session happens to expire. Runs
 * after auth:admin.
 */
class EnsureAdminIsActive
{
    public function __construct(private readonly PanelSessionService $panelSessions) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $admin = Auth::guard('admin')->user();

        if ($admin instanceof Admin && (! $admin->is_active || $this->panelSessions->isRevoked($admin, $request))) {
            $this->panelSessions->endRevoked($request);

            return redirect()->route('manager.login');
        }

        return $next($request);
    }
}
