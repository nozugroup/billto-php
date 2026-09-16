<?php

declare(strict_types=1);

namespace BillTo\Exceptions;

use BillTo\Http\ApiResponse;

/**
 * 429 - two distinct cases worth telling apart:
 *  - per-minute throttling (`retryAfter` set) - the SDK retries on its own and throws only once retries are exhausted;
 *  - exhausted plan quota (monthly invoice / API request limit) - `usage` and `upgradeUrl` are set,
 *    retrying will not help; buy a top-up package or wait for the next billing period.
 */
final class RateLimitException extends ApiException
{
    public readonly ?int $retryAfter;

    /** @var array{used: int, limit: int|null}|null */
    public readonly ?array $usage;

    public readonly ?string $upgradeUrl;

    public function __construct(string $message, int $status, ApiResponse $response)
    {
        parent::__construct($message, $status, $response);

        $retryAfter = $response->header('retry-after');
        $this->retryAfter = $retryAfter !== null && is_numeric($retryAfter) ? (int) $retryAfter : null;

        $usage = $response->json()['usage'] ?? null;
        $this->usage = is_array($usage) && array_key_exists('used', $usage)
            ? ['used' => (int) $usage['used'], 'limit' => isset($usage['limit']) ? (int) $usage['limit'] : null]
            : null;

        $upgradeUrl = $response->json()['upgrade_url'] ?? null;
        $this->upgradeUrl = is_string($upgradeUrl) ? $upgradeUrl : null;
    }

    /** Plan quota exhausted (not transient throttling) - retrying will not help. */
    public function isPlanLimit(): bool
    {
        return $this->retryAfter === null;
    }
}
