<?php

declare(strict_types=1);

use BillTo\Entities\EmailDelivery;
use BillTo\Entities\Invoice;
use BillTo\Entities\KsefStatus;
use BillTo\Entities\Order;
use BillTo\Entities\OrderActionResult;
use BillTo\Entities\ProfitabilityReport;
use BillTo\Entities\StockMovementReceipt;
use BillTo\Entities\VoteResult;
use BillTo\Enums\PaymentMethod;
use BillTo\Enums\StockMovementType;
use BillTo\Http\BinaryFile;
use Nyholm\Psr7\Response;

/**
 * Every resource method must hit the right HTTP method + path and unwrap the response.
 */
it('calls the expected endpoint', function (callable $call, string $method, string $path, ?array $body, array $responseBody, int $status = 200) {
    [$billto, $http] = fakeBillTo();
    $http->queue(jsonResponse($status, $responseBody));

    $call($billto);

    $request = $http->lastRequest();
    expect($request->getMethod())->toBe($method)
        ->and($request->getUri()->getPath())->toBe('/api/v1/'.$path);

    if ($body !== null) {
        expect($http->lastJsonBody())->toBe($body);
    }
})->with([
    'invoices.issue' => [fn ($b) => $b->invoices()->issue('i1'), 'POST', 'invoices/i1/issue', [], ['data' => ['id' => 'i1']]],
    'invoices.delete' => [fn ($b) => $b->invoices()->delete('i1'), 'DELETE', 'invoices/i1', null, [], 204],
    'invoices.ksefStatus' => [fn ($b) => $b->invoices()->ksefStatus('i1'), 'GET', 'invoices/i1/ksef', null, ['data' => ['sent' => false]]],
    'invoices.publicLink' => [fn ($b) => $b->invoices()->publicLink('i1'), 'POST', 'invoices/i1/public-link', [], ['data' => ['public_url' => 'u']]],
    'invoices.sendEmail' => [fn ($b) => $b->invoices()->sendEmail('i1', 'a@b.pl'), 'POST', 'invoices/i1/send-email', ['email' => 'a@b.pl'], ['data' => ['sent_to' => 'a@b.pl']], 202],
    'invoices.markPaid' => [fn ($b) => $b->invoices()->markPaid('i1', '2026-09-01', PaymentMethod::Card), 'POST', 'invoices/i1/mark-paid', ['paid_at' => '2026-09-01', 'payment_method' => 'card'], ['data' => ['id' => 'i1']]],
    'invoices.deletePayment' => [fn ($b) => $b->invoices()->deletePayment('i1', 'p1'), 'DELETE', 'invoices/i1/payments/p1', null, [], 204],
    'orders.confirm' => [fn ($b) => $b->orders()->confirm('o1'), 'POST', 'orders/o1/confirm', [], ['data' => ['id' => 'o1']]],
    'orders.markPaid' => [fn ($b) => $b->orders()->markPaid('o1'), 'POST', 'orders/o1/mark-paid', [], ['data' => ['id' => 'inv']], 201],
    'orders.issueKor' => [fn ($b) => $b->orders()->issueKor('o1', 's1'), 'POST', 'orders/o1/issue-kor', ['series_id' => 's1'], ['data' => ['id' => 'kor']], 201],
    'orders.issueAdvance' => [fn ($b) => $b->orders()->issueAdvance('o1', ['amount' => 100]), 'POST', 'orders/o1/issue-advance', ['amount' => 100], ['data' => ['id' => 'zal']], 201],
    'orders.sendConfirmation' => [fn ($b) => $b->orders()->sendConfirmation('o1'), 'POST', 'orders/o1/send-confirmation', [], ['data' => ['id' => 'o1']]],
    'contractors.insights' => [fn ($b) => $b->contractors()->insights('c1'), 'GET', 'contractors/c1/insights', null, ['data' => ['stats' => [], 'payment_score' => ['score' => 80]]]],
    'products.update' => [fn ($b) => $b->products()->update('p1', ['name' => 'X']), 'PUT', 'products/p1', ['name' => 'X'], ['data' => ['id' => 'p1']]],
    'incoming.accept' => [fn ($b) => $b->incomingInvoices()->accept('k1'), 'POST', 'incoming-invoices/k1/accept', [], ['data' => ['id' => 'k1']]],
    'incoming.reject' => [fn ($b) => $b->incomingInvoices()->reject('k1', 'dup'), 'POST', 'incoming-invoices/k1/reject', ['reason' => 'dup'], ['data' => ['id' => 'k1']]],
    'warehouse.warehouses' => [fn ($b) => $b->warehouse()->warehouses(), 'GET', 'warehouse/warehouses', null, ['data' => []]],
    'series.list' => [fn ($b) => $b->invoiceSeries()->list(), 'GET', 'invoice-series', null, ['data' => []]],
    'bank.list' => [fn ($b) => $b->bankAccounts()->list(), 'GET', 'bank-accounts', null, ['data' => []]],
    'rates.list' => [fn ($b) => $b->exchangeRates()->list('eur'), 'GET', 'exchange-rates', null, ['data' => []]],
    'priceGroups.list' => [fn ($b) => $b->priceGroups()->list(), 'GET', 'price-groups', null, ['data' => []]],
]);

it('creates an invoice and returns the entity', function () {
    [$billto, $http] = fakeBillTo();
    $http->queue(jsonResponse(201, ['data' => ['id' => 'inv-1', 'status' => 'issued', 'invoice_number' => 'FV/1/2026']]));

    $invoice = $billto->invoices()->create(['type' => 'VAT', 'issue' => true, 'items' => [['name' => 'A']]]);

    expect($invoice)->toBeInstanceOf(Invoice::class)
        ->and($invoice->invoice_number)->toBe('FV/1/2026')
        ->and($http->lastJsonBody()['issue'])->toBeTrue();
});

it('records a payment with compacted optional fields', function () {
    [$billto, $http] = fakeBillTo();
    $http->queue(jsonResponse(201, ['data' => ['id' => 'inv-1', 'paid_amount' => '100.00']]));

    $billto->invoices()->recordPayment('inv-1', 100, new DateTimeImmutable('2026-09-10'), PaymentMethod::Transfer);

    expect($http->lastJsonBody())->toBe(['amount' => 100, 'paid_at' => '2026-09-10', 'payment_method' => 'transfer']);
});

it('returns a KSeF status snapshot from sendToKsef (202)', function () {
    [$billto, $http] = fakeBillTo();
    $http->queue(jsonResponse(202, ['message' => 'queued', 'data' => ['sent' => true, 'ksef_number' => null, 'has_unresolved_errors' => false, 'errors' => []]]));

    $status = $billto->invoices()->sendToKsef('inv-1');

    expect($status)->toBeInstanceOf(KsefStatus::class)->and($status->isPending())->toBeTrue();
});

it('polls KSeF status until a number is assigned', function () {
    [$billto, $http] = fakeBillTo();
    $http->queue(
        jsonResponse(200, ['data' => ['sent' => true, 'ksef_number' => null, 'has_unresolved_errors' => false]]),
        jsonResponse(200, ['data' => ['sent' => true, 'ksef_number' => null, 'has_unresolved_errors' => false]]),
        jsonResponse(200, ['data' => ['sent' => true, 'ksef_number' => 'KSEF-1', 'has_unresolved_errors' => false]]),
    );
    $slept = [];

    $status = $billto->invoices()->waitForKsef('inv-1', 60, 5, static function (float $s) use (&$slept): void {
        $slept[] = $s;
    });

    expect($status->isAssigned())->toBeTrue()->and($slept)->toBe([5.0, 5.0])->and($http->requests)->toHaveCount(3);
});

it('downloads a PDF with the filename from Content-Disposition', function () {
    [$billto, $http] = fakeBillTo();
    $http->queue(new Response(200, [
        'Content-Type' => 'application/pdf',
        'Content-Disposition' => 'attachment; filename="FV-1-2026.pdf"',
    ], '%PDF-1.4 fake'));

    $file = $billto->invoices()->pdf('inv-1');

    expect($file)->toBeInstanceOf(BinaryFile::class)
        ->and($file->filename)->toBe('FV-1-2026.pdf')
        ->and($file->contentType)->toBe('application/pdf')
        ->and($file->content)->toStartWith('%PDF')
        ->and($http->lastRequest()->getHeaderLine('Accept'))->toBe('*/*');
});

it('passes the xml version as a query param', function () {
    [$billto, $http] = fakeBillTo();
    $http->queue(new Response(200, ['Content-Type' => 'application/xml'], '<Faktura/>'));

    $file = $billto->invoices()->xml('inv-1', 2);

    expect($http->lastRequest()->getUri()->getQuery())->toBe('version=2')->and($file->filename)->toBe('inv-1.xml');
});

it('sends an e-mail delivery request and reads the result', function () {
    [$billto, $http] = fakeBillTo();
    $http->queue(jsonResponse(202, ['data' => ['sent_to' => 'x@y.pl', 'sent_at' => '2026-09-14T10:00:00+02:00', 'public_url' => 'https://billto.pl/f/abc']]));

    $delivery = $billto->invoices()->sendEmail('inv-1');

    expect($delivery)->toBeInstanceOf(EmailDelivery::class)->and($delivery->public_url)->toBe('https://billto.pl/f/abc')
        ->and($http->lastJsonBody())->toBe([]);
});

it('returns order + meta for close, cancel and issueCorrection', function () {
    [$billto, $http] = fakeBillTo();
    $http->queue(
        jsonResponse(200, ['data' => ['id' => 'o1', 'status' => 'closed'], 'meta' => ['remaining_gross' => '10.00']]),
        jsonResponse(201, ['data' => ['id' => 'o2', 'corrects_order_id' => 'o1'], 'meta' => ['corrected_invoice_id' => null, 'overpayment_gross' => '50.00']]),
    );

    $closed = $billto->orders()->close('o1');
    expect($closed)->toBeInstanceOf(OrderActionResult::class)
        ->and($closed->order)->toBeInstanceOf(Order::class)
        ->and($closed->meta('remaining_gross'))->toBe('10.00');

    $correction = $billto->orders()->issueCorrection('o1', [['line_number' => 1, 'quantity' => 0]], 'return');
    expect($correction->order->isCorrecting())->toBeTrue()
        ->and($correction->overpaymentGross())->toBe(50.0)
        ->and($http->lastJsonBody())->toBe(['lines' => [['line_number' => 1, 'quantity' => 0]], 'reason' => 'return']);
});

it('records a DocuFlow vote and reads applied/reason', function () {
    [$billto, $http] = fakeBillTo();
    $http->queue(jsonResponse(202, ['applied' => false, 'reason' => 'no_member', 'data' => ['id' => 'k1', 'status' => 'pending']]));

    $result = $billto->incomingInvoices()->vote('k1', 'approved', 'jan@firma.pl', 'Jan');

    expect($result)->toBeInstanceOf(VoteResult::class)
        ->and($result->applied)->toBeFalse()
        ->and($result->reason)->toBe('no_member')
        ->and($result->invoice->id)->toBe('k1')
        ->and($http->lastJsonBody())->toBe(['decision' => 'approved', 'email' => 'jan@firma.pl', 'name' => 'Jan']);
});

it('builds the incremental sync query for incoming invoices', function () {
    [$billto, $http] = fakeBillTo();
    $http->queue(jsonResponse(200, paginated([], 1, 1, 0)));

    iterator_to_array($billto->incomingInvoices()->updatedSince(new DateTimeImmutable('2026-09-01T00:00:00+00:00'), 'pending'));

    $query = $http->lastRequest()->getUri()->getQuery();
    expect($query)->toContain('updated_since=2026-09-01T00%3A00%3A00%2B00%3A00')
        ->and($query)->toContain('status=pending')
        ->and($query)->toContain('per_page=200');
});

it('books a stock movement with an enum type and reads the receipt', function () {
    [$billto, $http] = fakeBillTo();
    $http->queue(jsonResponse(201, ['data' => ['id' => 'm1', 'type' => 'pz', 'quantity_after' => 12.0, 'replayed' => false]]));

    $receipt = $billto->warehouse()->move(StockMovementType::PZ, 2, ['sku' => 'ABC-1', 'external_id' => 'op-1']);

    expect($receipt)->toBeInstanceOf(StockMovementReceipt::class)
        ->and($receipt->replayed)->toBeFalse()
        ->and($http->lastJsonBody())->toBe(['type' => 'pz', 'quantity' => 2, 'sku' => 'ABC-1', 'external_id' => 'op-1']);
});

it('reads the profitability report from the top-level payload', function () {
    [$billto, $http] = fakeBillTo();
    $http->queue(jsonResponse(200, ['meta' => ['from' => '2026-09-01', 'to' => '2026-09-30', 'group_by' => 'product'], 'summary' => ['margin' => 10], 'groups' => [], 'below_cost' => []]));

    $report = $billto->reports()->profitability('2026-09-01', '2026-09-30', 'product');

    expect($report)->toBeInstanceOf(ProfitabilityReport::class)
        ->and($report->get('summary.margin'))->toBe(10)
        ->and($http->lastRequest()->getUri()->getQuery())->toBe('from=2026-09-01&to=2026-09-30&group_by=product');
});

it('finds the default series and bank account', function () {
    [$billto, $http] = fakeBillTo();
    $http->queue(
        jsonResponse(200, ['data' => [['id' => 's1', 'is_default' => false], ['id' => 's2', 'is_default' => true]]]),
        jsonResponse(200, ['data' => [['id' => 'b1', 'is_default' => true]]]),
    );

    expect($billto->invoiceSeries()->default()?->id)->toBe('s2')
        ->and($http->lastRequest()->getUri()->getQuery())->toBe('type=VAT')
        ->and($billto->bankAccounts()->default()?->id)->toBe('b1');
});

it('finds a contractor by exact tax number among search results', function () {
    [$billto, $http] = fakeBillTo();
    $http->queue(jsonResponse(200, paginated([['id' => 'c1', 'tax_number' => '5261040828'], ['id' => 'c2', 'tax_number' => '52610408281']], 1, 1)));

    $found = $billto->contractors()->findByTaxNumber('526-104-08-28');

    expect($found?->id)->toBe('c1')->and($http->lastRequest()->getUri()->getQuery())->toContain('search=5261040828');
});

it('resolves a product price for a contractor', function () {
    [$billto, $http] = fakeBillTo();
    $http->queue(jsonResponse(200, ['data' => ['product_id' => 'p1', 'unit_price' => 90.0, 'unit_price_gross' => 110.7, 'vat_type' => '23', 'source' => 'group_fixed', 'wdt' => false]]));

    $price = $billto->products()->price('p1', 'c1');

    expect($price->amount('unit_price'))->toBe(90.0)->and($http->lastRequest()->getUri()->getQuery())->toBe('contractor_id=c1');
});

it('passes send_email=false to mark-paid and issue-kor only when requested', function () {
    [$billto, $http] = fakeBillTo();
    $http->queue(
        jsonResponse(201, ['data' => ['id' => 'inv']]),
        jsonResponse(201, ['data' => ['id' => 'inv']]),
        jsonResponse(201, ['data' => ['id' => 'kor']]),
    );

    $billto->orders()->markPaid('o1');
    expect($http->lastJsonBody())->toBe([]);

    $billto->orders()->markPaid('o1', 'k', sendEmail: false);
    expect($http->lastJsonBody())->toBe(['send_email' => false]);

    $billto->orders()->issueKor('o2', null, null, sendEmail: false);
    expect($http->lastJsonBody())->toBe(['send_email' => false]);

    $http->queue(jsonResponse(201, ['data' => ['id' => 'inv']]));
    $billto->orders()->markPaid('o1', seriesId: 'ser-1');
    expect($http->lastJsonBody())->toBe(['series_id' => 'ser-1']);

    $http->queue(jsonResponse(201, ['data' => ['id' => 'oss']]));
    $billto->orders()->markPaid('o1', invoiceType: 'oss', ossVatType: '19');
    expect($http->lastJsonBody())->toBe(['invoice_type' => 'oss', 'oss_vat_type' => '19']);

    $http->queue(jsonResponse(201, ['data' => ['id' => 'unpaid']]));
    $billto->orders()->markPaid('o1', markPaid: false);
    expect($http->lastJsonBody())->toBe(['mark_paid' => false]);
});
