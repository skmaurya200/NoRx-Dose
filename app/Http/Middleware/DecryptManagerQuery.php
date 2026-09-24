<?php

namespace App\Http\Middleware;

use App\Support\ManagerQuery;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DecryptManagerQuery
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $query = $request->query->all();

        if ($query === []) {
            return $next($request);
        }

        $sealed = $query[ManagerQuery::PARAMETER] ?? null;
        $routeName = $request->route()?->getName();

        abort_unless(
            count($query) === 1
                && is_string($sealed)
                && is_string($routeName)
                && ($parameters = ManagerQuery::open($sealed, $routeName)) !== null,
            Response::HTTP_NOT_FOUND,
        );

        $request->query->replace($parameters);

        return $next($request);
    }
}
