<?php

namespace App\Exceptions\Auth;

use App\Exceptions\ApiException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Raised once an account has burned through its failed-attempt budget. The
 * password is not checked while a lock is active, so a locked account cannot be
 * brute-forced by continuing to guess.
 */
class AccountLockedException extends ApiException
{
    public function __construct(int $retryAfterSeconds)
    {
        parent::__construct(
            sprintf(
                'Too many failed sign-in attempts. Try again in %d minute(s).',
                (int) max(1, ceil($retryAfterSeconds / 60)),
            ),
            Response::HTTP_LOCKED,
            null,
            ['Retry-After' => (string) $retryAfterSeconds],
        );
    }
}
