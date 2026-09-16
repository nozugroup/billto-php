<?php

declare(strict_types=1);

namespace BillTo\Exceptions;

use BillTo\Http\ApiResponse;

/**
 * The API answered 2xx but in an unexpected shape (e.g. missing `data`).
 */
final class UnexpectedResponseException extends BillToException
{
    public function __construct(string $message, public readonly ApiResponse $response)
    {
        parent::__construct($message, $response->status);
    }
}
