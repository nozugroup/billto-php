<?php

declare(strict_types=1);

namespace BillTo\Entities;

/**
 * Invoice line item.
 *
 * @property-read int $line_number
 * @property-read string $name
 * @property-read string $quantity
 * @property-read string|null $units
 * @property-read string|null $unit_price
 * @property-read string|null $unit_price_gross
 * @property-read string $vat_type
 * @property-read string|null $product_id
 * @property-read string $net_amount
 * @property-read string $vat_amount
 * @property-read string $gross_amount
 * @property-read string|null $unit_cost
 * @property-read bool $is_before_correction
 */
final class InvoiceItem extends Entity {}
