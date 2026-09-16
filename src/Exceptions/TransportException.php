<?php

declare(strict_types=1);

namespace BillTo\Exceptions;

/**
 * Network / HTTP client failure - the request never reached the API or no response came back.
 */
final class TransportException extends BillToException
{
    public function __construct(string $message, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
