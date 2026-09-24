<?php

namespace App\Exceptions\Auth;

use App\Exceptions\ApiException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Only thrown after the password has already been verified. Checking this
 * before the password would tell an anonymous caller which emails exist.
 */
class AccountDisabledException extends ApiException
{
    public function __construct()
    {
        parent::__construct(
            'Your account is inactive. Please contact the administrator.',
            Response::HTTP_FORBIDDEN,
        );
    }
}
