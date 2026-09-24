<?php

namespace App\Exceptions\Catalogue;

use App\Exceptions\ApiException;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * A configured ceiling was hit - for example the gallery image limit. This is
 * a rule of the application, not a validation failure on a single field, so it
 * carries its own message rather than a field error bag.
 */
class LimitExceededException extends ApiException
{
    public function __construct(string $message)
    {
        parent::__construct($message, HttpResponse::HTTP_UNPROCESSABLE_ENTITY);
    }
}
