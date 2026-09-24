<?php

namespace App\Exceptions\Commerce;

use App\Exceptions\ApiException;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * The code exists but cannot be used on this basket - expired, used up, or the
 * order is under its minimum. 422 rather than 404 because the request was
 * well-formed and the customer can fix it by changing the basket.
 *
 * The message is written to be shown to the customer verbatim.
 */
class CouponNotApplicableException extends ApiException
{
    public function __construct(string $message)
    {
        parent::__construct($message, HttpResponse::HTTP_UNPROCESSABLE_ENTITY, [
            'coupon_code' => [$message],
        ]);
    }
}
