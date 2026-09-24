<?php

namespace App\Exceptions\Commerce;

use App\Exceptions\ApiException;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * The order cannot be placed as submitted: an empty basket, a product that has
 * since been unpublished, or a line whose stock ran out while the customer was
 * filling the form.
 *
 * Carries a field key where there is a sensible one, so the checkout page can
 * mark the offending input instead of only flashing a toast.
 */
class CheckoutFailedException extends ApiException
{
    /**
     * @param  array<string, array<int, string>>|null  $errors
     */
    public function __construct(string $message, ?array $errors = null)
    {
        parent::__construct($message, HttpResponse::HTTP_UNPROCESSABLE_ENTITY, $errors);
    }
}
