<?php

declare(strict_types=1);

/**
 * Incremental sync of incoming (cost) invoices from the KSeF inbox into your own system.
 *
 * Persist the `updated_since` cursor between runs (here: a temp file).
 */

require __DIR__.'/../vendor/autoload.php';

use BillTo\BillTo;

$billto = BillTo::create(getenv('BILLTO_TOKEN') ?: throw new RuntimeException('Set BILLTO_TOKEN'));

$cursorFile = sys_get_temp_dir().'/billto-incoming-cursor.txt';
$since = is_file($cursorFile) ? trim((string) file_get_contents($cursorFile)) : '2026-01-01T00:00:00+00:00';
$runStartedAt = (new DateTimeImmutable)->format(DATE_ATOM);

$count = 0;

foreach ($billto->incomingInvoices()->updatedSince($since) as $invoice) {
    $count++;

    printf(
        "%-8s %-30s %-40s %10s %s\n",
        $invoice->status,
        $invoice->ksef_number ?? '-',
        mb_substr((string) $invoice->seller_name, 0, 40),
        $invoice->gross_amount ?? '-',
        $invoice->currency,
    );

    // Your own persistence goes here, e.g. upsert by $invoice->id.
}

file_put_contents($cursorFile, $runStartedAt);
printf("Synced %d invoice(s); next run starts from %s\n", $count, $runStartedAt);
