<?php

declare(strict_types=1);

namespace BillTo\Exceptions;

use BillTo\Http\ApiResponse;

/**
 * API response with a 4xx/5xx status. Specific statuses have dedicated subclasses
 * (ValidationException, RateLimitException, ...), but this class catches any of them.
 */
class ApiException extends BillToException
{
    public function __construct(
        string $message,
        public readonly int $status,
        public readonly ApiResponse $response,
    ) {
        parent::__construct($message, $status);
    }

    /**
     * Raw response body decoded from JSON (null when it was not JSON).
     *
     * @return array<string, mixed>|null
     */
    public function body(): ?array
    {
        return $this->response->json();
    }

    /** Any top-level key from the response body, e.g. `upgrade_url` or `code`. */
    public function get(string $key): mixed
    {
        return $this->response->json()[$key] ?? null;
    }

    public function isClientError(): bool
    {
        return $this->status >= 400 && $this->status < 500;
    }

    public function isServerError(): bool
    {
        return $this->status >= 500;
    }
}
