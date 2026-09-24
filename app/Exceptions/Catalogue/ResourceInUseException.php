<?php

namespace App\Exceptions\Catalogue;

use App\Exceptions\ApiException;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Raised when a record cannot be removed because other records still depend on
 * it. Returned as 409 Conflict rather than letting the database throw a foreign
 * key violation, which would surface as an opaque 500 and leak the schema.
 */
class ResourceInUseException extends ApiException
{
    public function __construct(string $message)
    {
        parent::__construct($message, HttpResponse::HTTP_CONFLICT);
    }
}
