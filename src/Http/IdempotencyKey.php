<?php

declare(strict_types=1);

namespace BillTo\Http;

/**
 * Idempotency key generator (UUID v4). The BillTo API caches the first 2xx response for a
 * given key for 24 hours and replays it on repeat instead of performing the operation again.
 */
final class IdempotencyKey
{
    public static function generate(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0F) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3F) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    /**
     * Deterministic key derived from a caller-side operation id (e.g. a payment webhook id):
     * re-processing the same event reuses the API-side cached response.
     */
    public static function fromOperation(string $operationId, string $namespace = 'billto-php'): string
    {
        return hash('sha256', $namespace.'|'.$operationId);
    }
}
