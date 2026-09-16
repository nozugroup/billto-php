<?php

declare(strict_types=1);

use BillTo\BillTo;
use BillTo\Config;
use BillTo\Http\Transport;
use BillTo\Tests\Support\FakeHttpClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;

/**
 * Build an SDK client wired to an in-memory HTTP client. Returns [BillTo, FakeHttpClient].
 *
 * @return array{0: BillTo, 1: FakeHttpClient}
 */
function fakeBillTo(?Config $config = null): array
{
    $http = new FakeHttpClient;
    $factory = new Psr17Factory;
    $config ??= new Config('test-token', 'https://api.test/api/v1');

    $transport = (new Transport($config, $http, $factory, $factory))
        ->withSleeper(static function (float $seconds) use ($http): void {
            $http->sleeps[] = $seconds;
        })
        ->withKeyGenerator(static fn (): string => 'generated-key');

    return [new BillTo($config, $transport), $http];
}

/**
 * @param  array<string, mixed>  $body
 * @param  array<string, string>  $headers
 */
function jsonResponse(int $status, array $body = [], array $headers = []): Response
{
    return new Response(
        $status,
        ['Content-Type' => 'application/json'] + $headers,
        json_encode($body, JSON_THROW_ON_ERROR),
    );
}

/**
 * Laravel-style paginated payload.
 *
 * @param  list<array<string, mixed>>  $rows
 * @return array<string, mixed>
 */
function paginated(array $rows, int $currentPage, int $lastPage, ?int $total = null, int $perPage = 15): array
{
    return [
        'data' => $rows,
        'links' => [],
        'meta' => [
            'current_page' => $currentPage,
            'last_page' => $lastPage,
            'per_page' => $perPage,
            'total' => $total ?? count($rows),
        ],
    ];
}
