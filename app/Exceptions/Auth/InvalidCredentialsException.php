<?php

namespace App\Exceptions\Auth;

use App\Exceptions\ApiException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Deliberately identical whether the account exists, the password is wrong, or
 * the account is soft-deleted - anything more specific lets an attacker
 * enumerate valid admin emails.
 */
class InvalidCredentialsException extends ApiException
{
    public function __construct()
    {
        parent::__construct(
            'Invalid username or password.',
            Response::HTTP_UNAUTHORIZED,
        );
    }
}
