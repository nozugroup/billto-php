# BillTo PHP SDK

[![CI](https://github.com/nozugroup/billto-php/actions/workflows/ci.yml/badge.svg)](https://github.com/nozugroup/billto-php/actions/workflows/ci.yml)
[![Latest Version](https://img.shields.io/packagist/v/billto/billto-php.svg)](https://packagist.org/packages/billto/billto-php)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

Official PHP client for the [BillTo](https://billto.pl) API: sales invoices, KSeF submission,
orders (pay-then-invoice), contractors, products, warehouse and incoming (cost) invoices.

- PHP 8.2+, PSR-18 / PSR-17 - bring your own HTTP client (Guzzle, Symfony HttpClient, ...)
- Automatic `Idempotency-Key` on every mutation, retries with backoff for throttling and outages
- Typed exceptions per HTTP status, lazy pagination, IDE-friendly entities
- Optional Laravel service provider and facade

API reference: [billto.pl/developers](https://billto.pl/developers) - OpenAPI: [billto.pl/docs/api](https://billto.pl/docs/api)

## Installation

```bash
composer require billto/billto-php guzzlehttp/guzzle
```

Any `psr/http-client-implementation` works instead of Guzzle; it is discovered automatically.

## Quick start

Generate a team token in BillTo under **Settings -> API tokens** and pick the scopes you need.

```php
use BillTo\BillTo;
use BillTo\Enums\InvoiceType;
use BillTo\Enums\PaymentMethod;
use BillTo\Enums\VatRate;

$billto = BillTo::create('blto_...');

$invoice = $billto->invoices()->create([
    'type' => InvoiceType::VAT->value,
    'series_id' => $billto->invoiceSeries()->default()->id,
    'issue' => true,
    'issue_date' => '2026-09-14',
    'payment_method' => PaymentMethod::Transfer->value,
    'currency' => 'PLN',
    'amount_entry_mode' => 'net',
    'buyer' => ['name' => 'ACME sp. z o.o.', 'tax_type' => 'local', 'tax_number' => '5261040828'],
    'items' => [
        ['name' => 'Consulting', 'quantity' => 10, 'unit_price' => 150, 'vat_type' => VatRate::Rate23->value],
    ],
]);

echo $invoice->invoice_number;        // FV/1/09/2026
echo $invoice->totals['gross'];       // 1845.00

$billto->invoices()->pdf($invoice->id)->saveTo('/tmp/invoice.pdf');
```

Use `BillTo::sandbox($token)` against `sandbox.billto.pl`.

## Resources

| Method | Endpoints | Scope |
|---|---|---|
| `$billto->invoices()` | list/get/create/update/delete, issue, sendToKsef, ksefStatus, waitForKsef, pdf, xml, sendEmail, publicLink, recordPayment, markPaid, deletePayment | `invoices:*`, `ksef:send` |
| `$billto->orders()` | list/get/create/update/delete, confirm, close, cancel, sendConfirmation, pdf, issueAdvance, markPaid, issueCorrection, issueKor | `orders:*` |
| `$billto->contractors()` | list/get/create/update/delete, findByTaxNumber, insights | `contractors:*` |
| `$billto->products()` | list/get/create/update/delete, price | `products:*` |
| `$billto->priceGroups()` | list | `products:read` |
| `$billto->incomingInvoices()` | list/get, updatedSince, xml, accept, reject, vote | `incoming:*` |
| `$billto->warehouse()` | warehouses, stocks, movements, move/receive/issue | `warehouse:*` |
| `$billto->reports()` | profitability | `invoices:read` |
| `$billto->invoiceSeries()`, `bankAccounts()`, `exchangeRates()` | list, default, rate | none |

Request payloads are plain arrays mirroring the API documentation, so the OpenAPI reference is
the single source of truth for field names.

## Entities

Every response is wrapped in a lightweight entity. Fields are available as properties, array keys
or dot paths, and each entity documents its fields with `@property-read` for autocompletion.

```php
$invoice->invoice_number;
$invoice['totals']['gross'];
$invoice->get('ksef.number');
$invoice->amount('remaining_amount');   // float
$invoice->date('paid_at');              // ?DateTimeImmutable
$invoice->items();                      // list<InvoiceItem>
$invoice->toArray();
```

## Pagination

`list()` returns one `Page`; `all()` returns a lazy `Paginator` that fetches the next page only
when iteration reaches it.

```php
$page = $billto->invoices()->list(['status' => 'issued'], page: 1, perPage: 50);
$page->total; $page->lastPage; $page->hasMorePages();

foreach ($billto->invoices()->all(['date_from' => '2026-01-01']) as $invoice) {
    // ...
}

foreach ($billto->contractors()->all()->pages() as $page) {
    // one page at a time
}
```

## Identifying your integration

The API asks every integration to identify itself with a structured `User-Agent`, so that a
backwards-incompatible change can be announced to whoever maintains the software:

```
<Product>/<version> (+<contact>; compat=<api-version>)
```

The SDK sends its own identification by default, which is enough when you automate **your own**
invoicing. If you distribute software that runs on **other people's** BillTo accounts - a plugin,
a module, a hosted service - identify your product instead. Otherwise every installation looks
like `billto-php`, and when an endpoint is deprecated BillTo has to reach each of your customers
separately rather than notifying you once.

```php
$config = (new \BillTo\Config('blto_...'))
    ->identify('MyERP', '2.1.0', 'https://myerp.example/contact');

// MyERP/2.1.0 (+https://myerp.example/contact; compat=2026-09-15) billto-php/0.1.0
```

- **product** - a stable name; do not change it between releases, it is how your installations
  are grouped.
- **contact** - a working e-mail address or a page with a contact channel. Mandatory: a header
  without it names a product nobody can be notified about.
- **compat** - the API version you tested against. Informational; it targets change
  notifications and never selects API behaviour.

Requests that do not identify the integration currently succeed and come back with an
`X-Api-Client-Warning` header; when a cut-off date is announced it is sent as
`X-Api-Client-Required-From`. Watch for both in your logs.

## Idempotency and retries

Every `POST`/`PUT` gets a random `Idempotency-Key` unless you pass your own. The API replays the
first successful response for 24 hours, so a retried request never creates a second document.
Pass your own key when the operation is driven by an external event:

```php
use BillTo\Http\IdempotencyKey;

$invoice = $billto->orders()->markPaid($orderId, IdempotencyKey::fromOperation($webhookId));
```

Retries (default 2) apply to network errors, `502`/`503`/`504` and throttled `429` responses
carrying `Retry-After`. A `429` without `Retry-After` is an exhausted plan quota and is not
retried. Mutations without an idempotency key are never retried.

```php
$config = (new \BillTo\Config('blto_...'))->withMaxRetries(4)->withAutoIdempotency(false);
$billto = BillTo::fromConfig($config);
```

## Errors

All exceptions extend `BillTo\Exceptions\BillToException`.

| Exception | Status | Notes |
|---|---|---|
| `AuthenticationException` | 401 | missing / revoked token |
| `PaymentRequiredException` | 402 | no active plan; `upgradeUrl()` |
| `ForbiddenException` | 403 | `isInsufficientScope()`, `isNipConflict()`, module not in plan |
| `NotFoundException` | 404 | |
| `ConflictException` | 409 | already invoiced, KSeF submission in progress, approval workflow; `data()` |
| `ValidationException` | 422 | `errors`, `has($field)`, `first($field)`, `messages()` |
| `RateLimitException` | 429 | `retryAfter`, `isPlanLimit()`, `usage`, `upgradeUrl` |
| `ServerException` | 5xx | |
| `TransportException` | - | network failure |

```php
use BillTo\Exceptions\RateLimitException;
use BillTo\Exceptions\ValidationException;

try {
    $billto->invoices()->issue($id);
} catch (ValidationException $e) {
    $e->first('series_id');
} catch (RateLimitException $e) {
    if ($e->isPlanLimit()) {
        // $e->usage['used'] of $e->usage['limit'] - upgrade at $e->upgradeUrl
    }
}
```

## KSeF

```php
$billto->invoices()->sendToKsef($invoice->id);          // 202, number assigned asynchronously
$status = $billto->invoices()->waitForKsef($invoice->id, timeoutSeconds: 120);

if ($status->isAssigned()) {
    $status->ksef_number;
    $billto->invoices()->xml($invoice->id)->saveTo('fa3.xml');
} elseif ($status->hasErrors()) {
    $status->errors; // [{status_code, status_description, ksef_error_at, resolved_at}]
}
```

## Incoming (cost) invoices

```php
foreach ($billto->incomingInvoices()->updatedSince($lastRunAt, 'pending') as $invoice) {
    if (! $invoice->isInApprovalFlow()) {
        $billto->incomingInvoices()->accept($invoice->id);
    }
}
```

## Laravel

The service provider is auto-discovered. Publish the config and set `BILLTO_TOKEN` in `.env`:

```bash
php artisan vendor:publish --tag=billto-config
```

```php
use BillTo\Laravel\Facades\BillTo;

BillTo::invoices()->list();

// or inject \BillTo\BillTo into your services
```

## Custom HTTP client

```php
$billto = BillTo::create(
    token: 'blto_...',
    httpClient: new \GuzzleHttp\Client(['timeout' => 15]),
);
```

Any PSR-18 client and PSR-17 factories can be passed explicitly; otherwise they are discovered.

## Development

```bash
composer install
composer check      # pint --test, phpstan, pest
```

See [`examples/`](examples) for end-to-end scripts (issue an invoice, payment webhook, incremental sync).

Bug reports and pull requests: [github.com/nozugroup/billto-php](https://github.com/nozugroup/billto-php/issues).

## License

MIT. See [LICENSE](LICENSE).
