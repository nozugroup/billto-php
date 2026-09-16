<?php

declare(strict_types=1);

use BillTo\Config;
use BillTo\Exceptions\AuthenticationException;
use BillTo\Exceptions\ConflictException;
use BillTo\Exceptions\ForbiddenException;
use BillTo\Exceptions\NotFoundException;
use BillTo\Exceptions\PaymentRequiredException;
use BillTo\Exceptions\RateLimitException;
use BillTo\Exceptions\ServerException;
use BillTo\Exceptions\TransportException;
use BillTo\Exceptions\ValidationException;
use BillTo\Tests\Support\NetworkFailure;
use Nyholm\Psr7\Response;

it('sends bearer token, accept and user-agent headers', function () {
    [$billto, $http] = fakeBillTo();
    $http->queue(jsonResponse(200, ['data' => ['id' => 'inv-1']]));

    $billto->invoices()->get('inv-1');

    $request = $http->lastRequest();
    expect($request->getHeaderLine('Authorization'))->toBe('Bearer test-token')
        ->and($request->getHeaderLine('Accept'))->toBe('application/json')
        ->and($request->getHeaderLine('User-Agent'))->toStartWith('billto-php/')
        ->and((string) $request->getUri())->toBe('https://api.test/api/v1/invoices/inv-1');
});

it('encodes query params, drops nulls and normalises booleans and enums', function () {
    [$billto, $http] = fakeBillTo();
    $http->queue(jsonResponse(200, ['data' => []]));

    $billto->products()->list(['active_only' => false, 'search' => 'ka wa', 'kind' => null], 2, 25);

    $query = $http->lastRequest()->getUri()->getQuery();
    expect($query)->toContain('active_only=0')
        ->and($query)->toContain('search=ka%20wa')
        ->and($query)->toContain('page=2')
        ->and($query)->toContain('per_page=25')
        ->and($query)->not->toContain('kind');
});

it('adds a generated Idempotency-Key to POST and PUT but not to GET or DELETE', function () {
    [$billto, $http] = fakeBillTo();
    $http->queue(
        jsonResponse(201, ['data' => ['id' => 'c-1']]),
        jsonResponse(200, ['data' => ['id' => 'c-1']]),
        jsonResponse(200, ['data' => ['id' => 'c-1']]),
        new Response(204),
    );

    $billto->contractors()->create(['name' => 'ACME']);
    expect($http->lastRequest()->getHeaderLine('Idempotency-Key'))->toBe('generated-key');

    $billto->contractors()->update('c-1', ['name' => 'ACME 2']);
    expect($http->lastRequest()->getHeaderLine('Idempotency-Key'))->toBe('generated-key');

    $billto->contractors()->get('c-1');
    expect($http->lastRequest()->hasHeader('Idempotency-Key'))->toBeFalse();

    $billto->contractors()->delete('c-1');
    expect($http->lastRequest()->hasHeader('Idempotency-Key'))->toBeFalse();
});

it('prefers an explicit Idempotency-Key over the generated one', function () {
    [$billto, $http] = fakeBillTo();
    $http->queue(jsonResponse(201, ['data' => ['id' => 'inv-1']]));

    $billto->invoices()->create(['type' => 'VAT'], 'order-42');

    expect($http->lastRequest()->getHeaderLine('Idempotency-Key'))->toBe('order-42');
});

it('sends no Idempotency-Key when auto idempotency is disabled', function () {
    [$billto, $http] = fakeBillTo((new Config('t', 'https://api.test/api/v1'))->withAutoIdempotency(false));
    $http->queue(jsonResponse(201, ['data' => ['id' => 'inv-1']]));

    $billto->invoices()->create(['type' => 'VAT']);

    expect($http->lastRequest()->hasHeader('Idempotency-Key'))->toBeFalse();
});

it('serialises the JSON body with unescaped unicode', function () {
    [$billto, $http] = fakeBillTo();
    $http->queue(jsonResponse(201, ['data' => ['id' => 'c-1']]));

    $billto->contractors()->create(['name' => 'Żółć sp. z o.o.']);

    expect($http->lastRequest()->getHeaderLine('Content-Type'))->toBe('application/json')
        ->and((string) $http->lastRequest()->getBody())->toBe('{"name":"Żółć sp. z o.o."}');
});

it('maps every error status to its exception class', function (int $status, string $class) {
    [$billto, $http] = fakeBillTo((new Config('t', 'https://api.test/api/v1'))->withMaxRetries(0));
    $http->queue(jsonResponse($status, ['message' => 'boom']));

    expect(fn () => $billto->invoices()->get('x'))->toThrow($class, 'boom');
})->with([
    [401, AuthenticationException::class],
    [402, PaymentRequiredException::class],
    [403, ForbiddenException::class],
    [404, NotFoundException::class],
    [409, ConflictException::class],
    [422, ValidationException::class],
    [429, RateLimitException::class],
    [500, ServerException::class],
    [503, ServerException::class],
]);

it('exposes validation errors per field', function () {
    [$billto, $http] = fakeBillTo();
    $http->queue(jsonResponse(422, [
        'message' => 'The given data was invalid.',
        'errors' => ['items.0.vat_type' => ['Unsupported VAT rate.'], 'currency' => ['Required.', 'Must be 3 chars.']],
    ]));

    try {
        $billto->invoices()->create([]);
        $this->fail('Expected ValidationException');
    } catch (ValidationException $e) {
        expect($e->status)->toBe(422)
            ->and($e->has('items.0.vat_type'))->toBeTrue()
            ->and($e->first('currency'))->toBe('Required.')
            ->and($e->messages())->toHaveCount(3);
    }
});

it('retries a throttled 429 using Retry-After and then succeeds', function () {
    [$billto, $http] = fakeBillTo();
    $http->queue(
        jsonResponse(429, ['message' => 'Too Many Attempts.'], ['Retry-After' => '3']),
        jsonResponse(200, ['data' => ['id' => 'inv-1']]),
    );

    $invoice = $billto->invoices()->get('inv-1');

    expect($invoice->id)->toBe('inv-1')
        ->and($http->requests)->toHaveCount(2)
        ->and($http->sleeps)->toBe([3.0]);
});

it('does not retry a 429 that is a plan quota (no Retry-After)', function () {
    [$billto, $http] = fakeBillTo();
    $http->queue(jsonResponse(429, [
        'message' => 'Monthly invoice limit reached.',
        'usage' => ['used' => 50, 'limit' => 50],
        'upgrade_url' => 'https://billto.pl/t/settings/subscription',
    ]));

    try {
        $billto->invoices()->issue('inv-1');
        $this->fail('Expected RateLimitException');
    } catch (RateLimitException $e) {
        expect($e->isPlanLimit())->toBeTrue()
            ->and($e->usage)->toBe(['used' => 50, 'limit' => 50])
            ->and($e->upgradeUrl)->toBe('https://billto.pl/t/settings/subscription')
            ->and($http->requests)->toHaveCount(1);
    }
});

it('retries 503 with backoff and gives up after maxRetries', function () {
    [$billto, $http] = fakeBillTo();
    $http->queue(
        jsonResponse(503, ['message' => 'maintenance']),
        jsonResponse(503, ['message' => 'maintenance']),
        jsonResponse(503, ['message' => 'maintenance']),
    );

    expect(fn () => $billto->invoices()->get('inv-1'))->toThrow(ServerException::class);
    expect($http->requests)->toHaveCount(3)->and($http->sleeps)->toHaveCount(2);
});

it('does not retry a 500', function () {
    [$billto, $http] = fakeBillTo();
    $http->queue(jsonResponse(500, ['message' => 'crash']));

    expect(fn () => $billto->invoices()->get('inv-1'))->toThrow(ServerException::class);
    expect($http->requests)->toHaveCount(1);
});

it('retries network failures and wraps the final one in TransportException', function () {
    [$billto, $http] = fakeBillTo();
    $http->queue(new NetworkFailure('timeout'), new NetworkFailure('timeout'), new NetworkFailure('timeout'));

    expect(fn () => $billto->invoices()->get('inv-1'))->toThrow(TransportException::class, 'timeout');
    expect($http->requests)->toHaveCount(3);
});

it('never retries a mutation without an idempotency key', function () {
    [$billto, $http] = fakeBillTo((new Config('t', 'https://api.test/api/v1'))->withAutoIdempotency(false));
    $http->queue(jsonResponse(503, ['message' => 'maintenance']));

    expect(fn () => $billto->invoices()->create(['type' => 'VAT']))->toThrow(ServerException::class);
    expect($http->requests)->toHaveCount(1);
});

it('retries a keyed mutation and re-sends the same key', function () {
    [$billto, $http] = fakeBillTo();
    $http->queue(new NetworkFailure('reset'), jsonResponse(201, ['data' => ['id' => 'inv-9']]));

    $invoice = $billto->orders()->markPaid('o-1', 'webhook-777');

    expect($invoice->id)->toBe('inv-9')
        ->and($http->requests[0]->getHeaderLine('Idempotency-Key'))->toBe('webhook-777')
        ->and($http->requests[1]->getHeaderLine('Idempotency-Key'))->toBe('webhook-777');
});

it('detects insufficient scope and NIP conflict on 403', function () {
    [$billto, $http] = fakeBillTo();
    $http->queue(
        jsonResponse(403, ['message' => 'Insufficient token scope.']),
        jsonResponse(403, ['message' => 'Account blocked.', 'code' => 'nip_conflict']),
    );

    try {
        $billto->invoices()->get('a');
    } catch (ForbiddenException $e) {
        expect($e->isInsufficientScope())->toBeTrue()->and($e->isNipConflict())->toBeFalse();
    }

    try {
        $billto->invoices()->get('b');
    } catch (ForbiddenException $e) {
        expect($e->isNipConflict())->toBeTrue();
    }
});

it('falls back to a default message when the error body is not JSON', function () {
    [$billto, $http] = fakeBillTo();
    $http->queue(new Response(404, ['Content-Type' => 'text/html'], '<h1>Not found</h1>'));

    try {
        $billto->invoices()->get('missing');
    } catch (NotFoundException $e) {
        expect($e->getMessage())->toBe('Resource not found.')->and($e->body())->toBeNull();
    }
});
