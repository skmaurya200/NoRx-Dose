<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Single place that shapes every API response, so a client can rely on the
 * envelope never changing between modules:
 *
 *   { "success": bool, "message": string, "data": mixed|null, "errors": object|null }
 *
 * Controllers must not hand-roll response()->json() - route everything through
 * here or the shape drifts module by module.
 */
final class ApiResponse
{
    /**
     * @param  array<string, mixed>  $headers
     */
    public static function success(
        mixed $data = null,
        string $message = 'OK',
        int $status = HttpResponse::HTTP_OK,
        array $headers = [],
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'errors' => null,
        ], $status, $headers);
    }

    /**
     * @param  array<string, array<int, string>>|null  $errors
     * @param  array<string, mixed>  $headers
     */
    public static function error(
        string $message,
        int $status = HttpResponse::HTTP_BAD_REQUEST,
        ?array $errors = null,
        array $headers = [],
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => null,
            'errors' => $errors,
        ], $status, $headers);
    }
}
