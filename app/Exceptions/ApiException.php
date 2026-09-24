<?php

namespace App\Exceptions;

use App\Support\ApiResponse;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Base for every expected, client-facing API failure. Anything extending this
 * renders itself through the shared envelope, so the handler never has to know
 * about individual modules.
 *
 * Only throw this for failures a caller is meant to see. Genuine faults should
 * bubble up as ordinary exceptions and be reported.
 */
class ApiException extends Exception
{
    /**
     * @param  array<string, array<int, string>>|null  $errors
     * @param  array<string, string>  $headers
     */
    public function __construct(
        string $message,
        protected int $status = 400,
        protected ?array $errors = null,
        protected array $headers = [],
    ) {
        parent::__construct($message);
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    /**
     * @return array<string, array<int, string>>|null
     */
    public function getErrors(): ?array
    {
        return $this->errors;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function render(Request $request): JsonResponse
    {
        return ApiResponse::error(
            $this->getMessage(),
            $this->status,
            $this->errors,
            $this->headers,
        );
    }
}
