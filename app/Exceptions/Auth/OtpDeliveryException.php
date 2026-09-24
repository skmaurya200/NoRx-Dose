<?php

namespace App\Exceptions\Auth;

use App\Exceptions\ApiException;
use Symfony\Component\HttpFoundation\Response;

/**
 * No recipient is configured, or the mailer refused the message. Signing in
 * cannot continue without the code, and the caller is told so plainly - the
 * mail error itself goes to the log, never to the response.
 */
class OtpDeliveryException extends ApiException
{
    public function __construct()
    {
        parent::__construct(
            'The verification code could not be sent. Please contact the administrator.',
            Response::HTTP_SERVICE_UNAVAILABLE,
        );
    }
}
