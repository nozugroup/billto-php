<?php

declare(strict_types=1);

use BillTo\Entities\Invoice;
use BillTo\Entities\InvoiceItem;
use BillTo\Entities\InvoiceParty;
use BillTo\Entities\KsefStatus;
use BillTo\Enums\InvoiceStatus;
use BillTo\Enums\InvoiceType;
use BillTo\Enums\VatRate;

$invoiceRow = [
    'id' => 'inv-1',
    'type' => 'KOR',
    'status' => 'issued',
    'invoice_number' => 'FV/1/2026',
    'paid_at' => null,
    'remaining_amount' => '1230.00',
    'totals' => ['net' => '1000.00', 'vat' => '230.00', 'gross' => '1230.00', 'by_vat_rate' => []],
    'ksef' => ['is_ksef' => true, 'sent' => true, 'number' => '1234567890-20260914-ABCDEF-01', 'offline' => false, 'ksef_date' => '2026-09-14'],
    'items' => [['line_number' => 1, 'name' => 'Consulting', 'vat_type' => '23']],
    'buyer' => ['role' => 'buyer', 'name' => 'ACME'],
    'created_at' => '2026-09-14T10:00:00+02:00',
];

it('exposes attributes via property, array access and dot paths', function () use ($invoiceRow) {
    $invoice = new Invoice($invoiceRow);

    expect($invoice->invoice_number)->toBe('FV/1/2026')
        ->and($invoice['status'])->toBe('issued')
        ->and($invoice->get('totals.gross'))->toBe('1230.00')
        ->and($invoice->get('items.0.name'))->toBe('Consulting')
        ->and($invoice->get('missing.path', 'default'))->toBe('default')
        ->and($invoice->has('ksef.number'))->toBeTrue()
        ->and($invoice->has('nope'))->toBeFalse()
        ->and(isset($invoice->id))->toBeTrue()
        ->and(isset($invoice->paid_at))->toBeFalse();
});

it('offers typed helpers', function () use ($invoiceRow) {
    $invoice = new Invoice($invoiceRow);

    expect($invoice->typeEnum())->toBe(InvoiceType::KOR)
        ->and($invoice->statusEnum())->toBe(InvoiceStatus::Issued)
        ->and($invoice->isDraft())->toBeFalse()
        ->and($invoice->isPaid())->toBeFalse()
        ->and($invoice->grossTotal())->toBe(1230.0)
        ->and($invoice->remainingAmount())->toBe(1230.0)
        ->and($invoice->ksefNumber())->toBe('1234567890-20260914-ABCDEF-01')
        ->and($invoice->date('created_at'))->toBeInstanceOf(DateTimeImmutable::class)
        ->and($invoice->date('paid_at'))->toBeNull()
        ->and($invoice->items())->toHaveCount(1)
        ->and($invoice->items()[0])->toBeInstanceOf(InvoiceItem::class)
        ->and($invoice->buyer())->toBeInstanceOf(InvoiceParty::class)
        ->and($invoice->seller())->toBeNull();
});

it('is immutable and JSON serialisable', function () use ($invoiceRow) {
    $invoice = new Invoice($invoiceRow);

    expect(fn () => $invoice['id'] = 'x')->toThrow(LogicException::class)
        ->and(json_decode(json_encode($invoice, JSON_THROW_ON_ERROR), true))->toBe($invoiceRow)
        ->and($invoice->toArray())->toBe($invoiceRow);
});

it('interprets KSeF status', function () {
    $pending = new KsefStatus(['sent' => true, 'ksef_number' => null, 'has_unresolved_errors' => false, 'errors' => []]);
    $done = new KsefStatus(['sent' => true, 'ksef_number' => 'KSEF-1', 'has_unresolved_errors' => false, 'errors' => []]);
    $failed = new KsefStatus(['sent' => true, 'ksef_number' => null, 'has_unresolved_errors' => true, 'errors' => [['status_code' => 400]]]);

    expect($pending->isPending())->toBeTrue()->and($pending->isAssigned())->toBeFalse()
        ->and($done->isAssigned())->toBeTrue()->and($done->isPending())->toBeFalse()
        ->and($failed->hasErrors())->toBeTrue()->and($failed->isPending())->toBeFalse();
});

it('maps VAT rates to percentages', function () {
    expect(VatRate::Rate23->percent())->toBe(23.0)
        ->and(VatRate::ZeroWdt->percent())->toBe(0.0)
        ->and(VatRate::Exempt->percent())->toBeNull()
        ->and(VatRate::from('np I'))->toBe(VatRate::NotSubjectI);
});
