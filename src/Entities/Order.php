<?php

declare(strict_types=1);

namespace BillTo\Entities;

use BillTo\Enums\OrderStatus;

/**
 * Sales order.
 *
 * @property-read string $id
 * @property-read string|null $order_number
 * @property-read string $status draft|confirmed|closed|cancelled
 * @property-read string|null $corrects_order_id
 * @property-read string|null $correction_invoice_id
 * @property-read string $currency
 * @property-read string $order_date
 * @property-read string|null $notes
 * @property-read array{net: string, vat: string, gross: string} $totals
 * @property-read array{contractor_id: string|null, name: string|null, tax_number: string|null, email: string|null} $buyer
 * @property-read list<array<string, mixed>> $items
 * @property-read bool $is_fully_invoiced
 * @property-read list<array<string, mixed>>|null $payment_schedule
 * @property-read int|null $next_installment_index
 * @property-read list<array{id: string, invoice_number: string|null, type: string, status: string, paid_at: string|null}> $invoices
 * @property-read array{sent_to: string|null, sent_at: string|null} $email_delivery
 * @property-read string $created_at
 * @property-read string $updated_at
 */
final class Order extends Entity
{
    public function statusEnum(): ?OrderStatus
    {
        return OrderStatus::tryFrom((string) $this->status);
    }

    public function isCorrecting(): bool
    {
        return $this->corrects_order_id !== null;
    }

    public function grossTotal(): ?float
    {
        return $this->amount('totals.gross');
    }
}
