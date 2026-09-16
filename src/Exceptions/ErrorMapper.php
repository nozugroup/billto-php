<?php

declare(strict_types=1);

namespace BillTo\Exceptions;

use BillTo\Http\ApiResponse;

/**
 * Maps a 4xx/5xx response to the matching exception class.
 */
final class ErrorMapper
{
    public static function map(ApiResponse $response): ApiException
    {
        $status = $response->status;
        $message = $response->message() ?? self::defaultMessage($status);

        return match (true) {
            $status === 401 => new AuthenticationException($message, $status, $response),
            $status === 402 => new PaymentRequiredException($message, $status, $response),
            $status === 403 => new ForbiddenException($message, $status, $response),
            $status === 404 => new NotFoundException($message, $status, $response),
            $status === 409 => new ConflictException($message, $status, $response),
            $status === 422 => new ValidationException($message, $status, $response),
            $status === 429 => new RateLimitException($message, $status, $response),
            $status >= 500 => new ServerException($message, $status, $response),
            default => new ApiException($message, $status, $response),
        };
    }

    private static function defaultMessage(int $status): string
    {
        return match ($status) {
            400 => 'Bad request.',
            401 => 'Unauthenticated - check the API token.',
            402 => 'This operation requires an active plan.',
            403 => 'Forbidden.',
            404 => 'Resource not found.',
            409 => 'Resource state conflict.',
            422 => 'The given data was invalid.',
            429 => 'Rate limit exceeded.',
            default => $status >= 500 ? 'BillTo server error.' : "API error (HTTP {$status}).",
        };
    }
}
