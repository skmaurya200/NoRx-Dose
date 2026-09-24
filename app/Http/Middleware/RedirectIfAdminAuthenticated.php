<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps a signed-in admin off the login screen.
 */
class RedirectIfAdminAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::guard('admin')->check()) {
            return redirect()->route('manager.dashboard');
        }

        return $next($request);
    }
}
