<?php

use App\Exceptions\ApiException;
use App\Http\Middleware\EnsureAdmin;
use App\Http\Middleware\EnsureAdminIsActive;
use App\Http\Middleware\LogManagerActivity;
use App\Http\Middleware\RedirectIfAdminAuthenticated;
use App\Http\Middleware\TrackAdminActivity;
use App\Http\Middleware\TrackAttribution;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Lets same-origin browser calls to /api/* authenticate from the admin
        // session cookie instead of a bearer token, and brings CSRF validation
        // with it. Without this the panel would have to keep a token in JS.
        $middleware->statefulApi();

        // Every storefront page view is a chance to learn where a visitor came
        // from. Appended to the web group so it runs after the session and the
        // cookie encryption it depends on.
        $middleware->web(append: [
            TrackAttribution::class,
        ]);

        // Writes made through the back-office API land in the activity log.
        // It reads the route and the response, never the request body.
        $middleware->api(append: [
            LogManagerActivity::class,
        ]);

        $middleware->alias([
            'admin' => EnsureAdmin::class,
            'admin.active' => EnsureAdminIsActive::class,
            'admin.track' => TrackAdminActivity::class,
            'admin.guest' => RedirectIfAdminAuthenticated::class,
        ]);

        // Unauthenticated web traffic goes to the admin login screen, never to
        // the storefront's (nonexistent) "login" route.
        $middleware->redirectGuestsTo(fn () => route('manager.login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Expected, client-facing failures render themselves and are not worth
        // reporting - they are a normal part of the request cycle.
        $exceptions->dontReport(ApiException::class);

        $exceptions->report(function (Throwable $exception) {
            if (request()->is('api/chat/*')) {
                Log::error('Chat request exception.', [
                    'error_type' => $exception::class,
                ]);

                return false;
            }

            return null;
        });

        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null; // fall through to the normal HTML handler
            }

            return match (true) {
                $request->is('api/chat/*') && $e instanceof LockTimeoutException => ApiResponse::error(
                    'Another chat request is still processing. Please try again shortly.', 429, null,
                    ['Retry-After' => '2', 'Cache-Control' => 'private, no-store'],
                ),
                $e instanceof ApiException => null, // renders itself

                $e instanceof ValidationException => ApiResponse::error(
                    'The given data was invalid.',
                    HttpResponse::HTTP_UNPROCESSABLE_ENTITY,
                    $e->errors(),
                ),

                $e instanceof AuthenticationException => ApiResponse::error(
                    'Authentication required.',
                    HttpResponse::HTTP_UNAUTHORIZED,
                ),

                $e instanceof AuthorizationException => ApiResponse::error(
                    'You are not allowed to perform this action.',
                    HttpResponse::HTTP_FORBIDDEN,
                ),

                $e instanceof ModelNotFoundException => ApiResponse::error(
                    'The requested resource was not found.',
                    HttpResponse::HTTP_NOT_FOUND,
                ),

                $e instanceof NotFoundHttpException => ApiResponse::error(
                    'Endpoint not found.',
                    HttpResponse::HTTP_NOT_FOUND,
                ),

                $e instanceof MethodNotAllowedException => ApiResponse::error(
                    'Method not allowed for this endpoint.',
                    HttpResponse::HTTP_METHOD_NOT_ALLOWED,
                ),

                // Throttle and any other HTTP exception keep their own status
                // and headers (Retry-After included).
                $e instanceof HttpExceptionInterface => ApiResponse::error(
                    $e->getMessage() !== '' ? $e->getMessage() : 'Request failed.',
                    $e->getStatusCode(),
                    null,
                    $e->getHeaders(),
                ),

                // Anything unplanned: the real message and stack trace go to the
                // log, never to the client, so a driver error cannot leak the
                // schema or credentials. debug builds still show detail.
                default => ApiResponse::error(
                    config('app.debug') && ! $request->is('api/chat/*') ? $e->getMessage() : 'Something went wrong. Please try again.',
                    HttpResponse::HTTP_INTERNAL_SERVER_ERROR,
                ),
            };
        });
    })->create();
