<?php

declare(strict_types=1);

namespace BillTo\Entities;

/**
 * Payment recorded against an invoice.
 *
 * @property-read string $id
 * @property-read string $amount
 * @property-read string $paid_at
 * @property-read string|null $payment_method
 * @property-read string|null $note
 * @property-read string $created_at
 */
final class InvoicePayment extends Entity {}
