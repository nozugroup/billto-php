<?php

declare(strict_types=1);

namespace BillTo\Entities;

/**
 * Result of an order action that returns `meta` next to the order
 * (close: `remaining_gross`; cancel: `invoices_requiring_correction`;
 * correction: `corrected_invoice_id`, `overpayment_gross`).
 */
final class OrderActionResult
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly Order $order,
        public readonly array $meta,
    ) {}

    public function meta(string $key, mixed $default = null): mixed
    {
        return $this->meta[$key] ?? $default;
    }

    /** Advance overpayment after a scope reduction (advance-path correction). */
    public function overpaymentGross(): ?float
    {
        $value = $this->meta['overpayment_gross'] ?? null;

        return is_numeric($value) ? (float) $value : null;
    }
}
