<?php

namespace App\Exceptions\Auth;

use App\Exceptions\ApiException;
use Symfony\Component\HttpFoundation\Response;

/**
 * The per-IP + per-identifier throttle, checked before the database is touched
 * so a flood of guesses cannot be used to probe for valid accounts by timing.
 * Also raised by the one-time code limits (wrong codes, re-sends, new codes).
 */
class TooManyAttemptsException extends ApiException
{
    public function __construct(int $retryAfterSeconds)
    {
        parent::__construct(
            'Too many attempts. Please try again later.',
            Response::HTTP_TOO_MANY_REQUESTS,
            null,
            ['Retry-After' => (string) $retryAfterSeconds],
        );
    }
}
