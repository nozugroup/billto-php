<?php

declare(strict_types=1);

/**
 * Pay-then-invoice: a payment provider webhook confirms an order was paid, BillTo issues the invoice.
 *
 * The webhook id is used as the Idempotency-Key, so a redelivered webhook never issues a second invoice.
 */

require __DIR__.'/../vendor/autoload.php';

use BillTo\BillTo;
use BillTo\Exceptions\ConflictException;
use BillTo\Exceptions\RateLimitException;
use BillTo\Http\IdempotencyKey;

$billto = BillTo::create(getenv('BILLTO_TOKEN') ?: throw new RuntimeException('Set BILLTO_TOKEN'));

// Pretend these came from the webhook payload.
$webhookId = 'evt_01J9X…';
$billtoOrderId = '019a1b2c-…';

try {
    $invoice = $billto->orders()->markPaid($billtoOrderId, IdempotencyKey::fromOperation($webhookId));

    printf("Invoice %s issued for order %s\n", $invoice->invoice_number, $billtoOrderId);

    // Optional: send it to KSeF right away and wait for the number.
    $billto->invoices()->sendToKsef($invoice->id, IdempotencyKey::fromOperation($webhookId, 'ksef'));
    $status = $billto->invoices()->waitForKsef($invoice->id, timeoutSeconds: 90);

    echo $status->isAssigned()
        ? "KSeF number: {$status->ksef_number}\n"
        : 'KSeF still pending or failed: '.json_encode($status->errors, JSON_UNESCAPED_UNICODE)."\n";
} catch (ConflictException $e) {
    // Order already invoiced by an earlier (non-keyed) call - nothing to do.
    echo "Already invoiced: {$e->getMessage()}\n";
} catch (RateLimitException $e) {
    if ($e->isPlanLimit()) {
        echo "Monthly limit reached ({$e->usage['used']}/{$e->usage['limit']}). Upgrade: {$e->upgradeUrl}\n";
    } else {
        echo "Throttled, retry after {$e->retryAfter}s\n";
    }
}
