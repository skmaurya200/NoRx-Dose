<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class ProtectChat
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            abort_unless(config('chat.enabled'), 404);
            $response = $next($request);
        } catch (Throwable $exception) {
            if ($exception instanceof HttpExceptionInterface || $exception instanceof ValidationException || $exception instanceof ModelNotFoundException) {
                $response = app(ExceptionHandler::class)->render($request, $exception);
            } else {
                Log::error('Chat request failed.', ['error_type' => $exception::class]);
                $response = ApiResponse::error('Sorry, something went wrong. Please try again.', 500);
            }
        }
        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }
}
