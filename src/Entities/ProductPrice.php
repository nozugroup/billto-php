<?php

declare(strict_types=1);

namespace BillTo\Entities;

/**
 * Product price resolved for a given buyer (price group, WDT).
 *
 * @property-read string $product_id
 * @property-read float|int $unit_price
 * @property-read float|int $unit_price_gross
 * @property-read string $vat_type
 * @property-read string $source base|group_fixed|group_markup|...
 * @property-read bool $wdt
 */
final class ProductPrice extends Entity {}
