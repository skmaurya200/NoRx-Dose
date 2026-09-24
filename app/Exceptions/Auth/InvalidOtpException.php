<?php

namespace App\Exceptions\Auth;

use App\Exceptions\ApiException;
use Symfony\Component\HttpFoundation\Response;

/**
 * One message for a wrong code, an expired code, a used code, a replaced code
 * and a challenge that never existed - which of those it was is nobody's
 * business but the log's.
 */
class InvalidOtpException extends ApiException
{
    public function __construct()
    {
        parent::__construct(
            'Invalid or expired OTP.',
            Response::HTTP_UNPROCESSABLE_ENTITY,
            ['code' => ['Invalid or expired OTP.']],
        );
    }
}
