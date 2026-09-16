<?php

declare(strict_types=1);

namespace BillTo\Entities;

use BillTo\Enums\InvoiceStatus;
use BillTo\Enums\InvoiceType;

/**
 * Sales invoice.
 *
 * @property-read string $id
 * @property-read string $type VAT|KOR|ZAL|ROZ|KOR_ZAL|KOR_ROZ|OSS|KOR_OSS
 * @property-read string $status draft|issued|ksef_issued|ksef_issued_offline|cancelled
 * @property-read string|null $invoice_number
 * @property-read string|null $series_id
 * @property-read string|null $order_id
 * @property-read int|null $order_installment_index
 * @property-read string|null $corrected_invoice_id
 * @property-read bool $is_correction
 * @property-read string|null $buyer_contractor_id
 * @property-read string $issue_date
 * @property-read string|null $sales_date
 * @property-read string|null $payment_method
 * @property-read string|null $payment_date
 * @property-read string $currency
 * @property-read string $amount_entry_mode net|gross
 * @property-read string|null $fx_rate
 * @property-read string|null $fx_rate_date
 * @property-read bool $split_payment
 * @property-read bool $cash_method
 * @property-read string|null $bank_number
 * @property-read string|null $bank_name
 * @property-read string|null $bank_swift
 * @property-read string|null $notes
 * @property-read string|null $additional_description
 * @property-read array{net: string, vat: string, gross: string, by_vat_rate: list<array{vat_type: string, net: string, vat: string, gross: string}>} $totals
 * @property-read array{basis: string, net: string, vat: string, gross: string}|null $economic
 * @property-read array<string, mixed>|null $correction
 * @property-read list<array<string, mixed>> $corrections
 * @property-read array{is_ksef: bool, sent: bool, number: string|null, offline: bool, ksef_date: string|null} $ksef
 * @property-read string|null $paid_at
 * @property-read string $payment_status
 * @property-read string $paid_amount
 * @property-read string $remaining_amount
 * @property-read string|null $cancelled_at
 * @property-read string|null $cancellation_reason
 * @property-read list<array<string, mixed>> $payments
 * @property-read string|null $public_url
 * @property-read array{sent_to: string|null, sent_at: string|null, first_viewed_at: string|null} $email_delivery
 * @property-read array<string, mixed>|null $seller
 * @property-read array<string, mixed>|null $buyer
 * @property-read list<array<string, mixed>> $third_parties
 * @property-read list<array<string, mixed>> $items
 * @property-read array<string, mixed>|null $margin
 * @property-read string $created_at
 * @property-read string $updated_at
 */
final class Invoice extends Entity
{
    public function typeEnum(): ?InvoiceType
    {
        return InvoiceType::tryFrom((string) $this->type);
    }

    public function statusEnum(): ?InvoiceStatus
    {
        return InvoiceStatus::tryFrom((string) $this->status);
    }

    public function isDraft(): bool
    {
        return $this->status === InvoiceStatus::Draft->value;
    }

    public function isPaid(): bool
    {
        return $this->paid_at !== null;
    }

    public function grossTotal(): ?float
    {
        return $this->amount('totals.gross');
    }

    public function remainingAmount(): ?float
    {
        return $this->amount('remaining_amount');
    }

    public function ksefNumber(): ?string
    {
        $number = $this->get('ksef.number');

        return is_string($number) ? $number : null;
    }

    /** @return list<InvoiceItem> */
    public function items(): array
    {
        return $this->many('items', InvoiceItem::class);
    }

    /** @return list<InvoicePayment> */
    public function payments(): array
    {
        return $this->many('payments', InvoicePayment::class);
    }

    public function seller(): ?InvoiceParty
    {
        return $this->one('seller', InvoiceParty::class);
    }

    public function buyer(): ?InvoiceParty
    {
        return $this->one('buyer', InvoiceParty::class);
    }
}
