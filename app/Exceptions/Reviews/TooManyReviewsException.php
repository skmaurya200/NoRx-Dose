<?php

namespace App\Exceptions\Reviews;

use App\Exceptions\ApiException;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * One address has filed more reviews today than a customer plausibly has to
 * say. 429 rather than 422: the submission was fine, there have just been too
 * many of them, and it will be accepted again tomorrow.
 */
class TooManyReviewsException extends ApiException
{
    public function __construct()
    {
        parent::__construct(
            'You have already sent us several reviews today. Please try again tomorrow.',
            HttpResponse::HTTP_TOO_MANY_REQUESTS,
        );
    }
}
