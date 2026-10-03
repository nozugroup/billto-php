<?php

declare(strict_types=1);

use BillTo\BillTo;
use BillTo\Config;
use BillTo\Entities\Invoice;
use BillTo\Entities\InvoiceParty;
use BillTo\Entities\Order;
use BillTo\Exceptions\ValidationException;
use BillTo\Http\ApiResponse;
use BillTo\Tests\Support\FakeHttpClient;
use BillTo\Tests\Support\NetworkFailure;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;

it('overrides pagination filters while retaining business filters', function () {
    [$billto, $http] = fakeBillTo();
    $http->queue(jsonResponse(200, paginated([['id' => '1']], 1, 2)), jsonResponse(200, paginated([['id' => '2']], 2, 2)));
    $items = $billto->invoices()->all(['page' => 1, 'per_page' => 1, 'status' => 'issued'], 50)->collect();
    expect(array_map(fn ($item) => $item->id, $items))->toBe(['1', '2']);
    parse_str($http->requests[1]->getUri()->getQuery(), $query);
    expect($query)->toBe(['page' => '2', 'per_page' => '50', 'status' => 'issued']);
});

it('uses explicit page arguments across every paginated resource', function (string $resource, string $method) {
    [$billto, $http] = fakeBillTo();
    $http->queue(jsonResponse(200, paginated([], 3, 3)));
    $billto->{$resource}()->{$method}(['page' => 1, 'per_page' => 1], 3, 50);
    parse_str($http->lastRequest()->getUri()->getQuery(), $query);
    expect($query)->toBe(['page' => '3', 'per_page' => '50']);
})->with([
    ['invoices', 'list'], ['orders', 'list'], ['contractors', 'list'], ['products', 'list'],
    ['incomingInvoices', 'list'], ['warehouse', 'stocks'], ['warehouse', 'movements'],
]);

it('stops before yielding a repeated page returned by the server', function () {
    [$billto, $http] = fakeBillTo();
    $http->queue(jsonResponse(200, paginated([['id' => '1']], 1, 2)), jsonResponse(200, paginated([['id' => '1']], 1, 2)));
    $ids = [];
    expect(function () use ($billto, &$ids): void {
        foreach ($billto->invoices()->all() as $item) {
            $ids[] = $item->id;
        }
    })->toThrow(UnexpectedValueException::class);
    expect($ids)->toBe(['1'])->and($http->requests)->toHaveCount(2);
});

it('encodes empty request objects without converting nested arrays to objects', function () {
    [$billto, $http] = fakeBillTo();
    $http->queue(jsonResponse(200, ['data' => []]), jsonResponse(200, ['data' => []]));
    $billto->invoices()->sendEmail('i');
    expect((string) $http->lastRequest()->getBody())->toBe('{}');
    $billto->invoices()->create(['items' => [], 'notes' => 'Łódź']);
    expect((string) $http->lastRequest()->getBody())->toBe('{"items":[],"notes":"Łódź"}');
});

it('exposes response headers for retries, successes, downloads and API errors', function () {
    $http = new FakeHttpClient;
    $factory = new Psr17Factory;
    $seen = [];
    $config = (new Config('t', retryBaseDelay: 0))->withCorrelationId('job-123')->withMaxRetries(1)->identify('app', '1', 'a@example.com');
    $billto = BillTo::fromConfig($config, $http, $factory, $factory, function (ApiResponse $response) use (&$seen): void {
        $seen[] = $response->header('X-Trace-Id');
    });
    $http->queue(
        jsonResponse(503, [], ['X-Trace-Id' => 'retry']), jsonResponse(200, ['data' => ['id' => 'i']], ['X-Trace-Id' => 'ok']),
        new Response(200, ['X-Trace-Id' => 'pdf'], '%PDF'), jsonResponse(422, [], ['X-Trace-Id' => 'error']),
    );
    expect($billto->invoices()->get('i')->id)->toBe('i');
    $billto->invoices()->pdf('i');
    expect(fn () => $billto->invoices()->create([]))->toThrow(ValidationException::class);
    expect($seen)->toBe(['retry', 'ok', 'pdf', 'error']);
    foreach ($http->requests as $request) {
        expect($request->getHeaderLine('X-Correlation-Id'))->toBe('job-123');
    }
    expect($config->withCorrelationId(null)->correlationId)->toBeNull();
});

it('does not retry a mutation when its observer throws a transport-shaped exception', function () {
    $http = new FakeHttpClient;
    $factory = new Psr17Factory;
    $billto = BillTo::create('t', httpClient: $http, requestFactory: $factory, streamFactory: $factory, onResponse: function (ApiResponse $response): void {
        throw new NetworkFailure('logger failed');
    });
    $http->queue(jsonResponse(200, ['data' => []]));
    expect(fn () => $billto->invoices()->create([]))->toThrow(NetworkFailure::class, 'logger failed');
    expect($http->requests)->toHaveCount(1);
});

it('rejects invalid correlation IDs', function (string $value) {
    expect(fn () => new Config('t', correlationId: $value))->toThrow(InvalidArgumentException::class);
})->with(['', ' ', "a\r\nb", 'ą']);

it('preserves correlation IDs through all configuration withers', function () {
    $config = (new Config('t'))->withCorrelationId('job')->withBaseUrl('https://example.com')->withMaxRetries(1)->withAutoIdempotency(false)->withUserAgent('test/1');
    expect($config->correlationId)->toBe('job');
});

it('accepts numeric OSS tax rates without coercion', function () {
    [$billto, $http] = fakeBillTo();
    $http->queue(jsonResponse(200, ['data' => []]));
    $billto->orders()->markPaid('o', ossVatType: 19.5);
    expect($http->lastJsonBody()['oss_vat_type'])->toBe(19.5);
});

it('exposes integration fields and missing optional relations', function () {
    $order = new Order(['source' => 'shop', 'external_id' => null, 'display_number' => '#1', 'integration_warnings' => ['missing_email']]);
    expect($order->source)->toBe('shop')->and($order->integration_warnings)->toBe(['missing_email'])->and($order->items)->toBeNull();
    $invoice = new Invoice(['effective_target' => 'ksef', 'created_at' => null]);
    expect($invoice->effective_target)->toBe('ksef')->and($invoice->items())->toBe([])->and($invoice->buyer())->toBeNull();
    $party = new InvoiceParty(['address_line_1' => 'Łódź', 'address_line_2' => null]);
    expect($party->address_line_1)->toBe('Łódź')->and($party->address_line_2)->toBeNull();
});
