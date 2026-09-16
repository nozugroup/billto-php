<?php

declare(strict_types=1);

/**
 * Create and issue a VAT invoice for an existing contractor, then download the PDF.
 *
 * Run: BILLTO_TOKEN=blto_... php examples/issue-invoice.php
 */

require __DIR__.'/../vendor/autoload.php';

use BillTo\BillTo;
use BillTo\Enums\InvoiceType;
use BillTo\Enums\PaymentMethod;
use BillTo\Enums\VatRate;
use BillTo\Exceptions\BillToException;
use BillTo\Exceptions\ValidationException;

$billto = BillTo::create(getenv('BILLTO_TOKEN') ?: throw new RuntimeException('Set BILLTO_TOKEN'));

try {
    $series = $billto->invoiceSeries()->default(InvoiceType::VAT)
        ?? throw new RuntimeException('No default VAT series configured.');

    $buyer = $billto->contractors()->findByTaxNumber('5261040828')
        ?? $billto->contractors()->create([
            'name' => 'ACME sp. z o.o.',
            'tax_registration_type' => 'local',
            'tax_number' => '5261040828',
            'email' => 'faktury@acme.example',
            'addresses' => [[
                'type' => 'registered',
                'postal_code' => '00-001',
                'locality' => 'Warszawa',
                'street' => 'Marszałkowska',
                'building_number' => '1',
            ]],
        ]);

    $invoice = $billto->invoices()->create([
        'type' => InvoiceType::VAT->value,
        'series_id' => $series->id,
        'issue' => true,
        'issue_date' => date('Y-m-d'),
        'sales_date' => date('Y-m-d'),
        'payment_date' => date('Y-m-d', strtotime('+14 days')),
        'payment_method' => PaymentMethod::Transfer->value,
        'currency' => 'PLN',
        'amount_entry_mode' => 'net',
        'buyer_contractor_id' => $buyer->id,
        'items' => [
            ['name' => 'Consulting services', 'quantity' => 10, 'units' => 'h', 'unit_price' => 150.00, 'vat_type' => VatRate::Rate23->value],
        ],
    ], idempotencyKey: 'example-order-1001');

    printf("Issued %s for %s PLN gross\n", $invoice->invoice_number, $invoice->totals['gross']);

    $pdf = $billto->invoices()->pdf($invoice->id);
    $path = $pdf->saveIn(sys_get_temp_dir());
    printf("PDF saved to %s (%d bytes)\n", $path, $pdf->size());
} catch (ValidationException $e) {
    fwrite(STDERR, "Validation failed: {$e->getMessage()}\n");

    foreach ($e->errors as $field => $messages) {
        fwrite(STDERR, "  {$field}: ".implode(' ', $messages)."\n");
    }

    exit(1);
} catch (BillToException $e) {
    fwrite(STDERR, get_class($e).": {$e->getMessage()}\n");
    exit(1);
}
