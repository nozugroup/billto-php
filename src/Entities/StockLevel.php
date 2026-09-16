<?php

declare(strict_types=1);

namespace BillTo\Entities;

/**
 * Stock level (warehouse x product x location).
 *
 * @property-read string $id
 * @property-read string $warehouse_id
 * @property-read string|null $warehouse
 * @property-read string $product_id
 * @property-read string|null $product
 * @property-read string|null $sku
 * @property-read string|null $ean
 * @property-read string|null $location
 * @property-read float|int $quantity
 * @property-read float|int $reserved
 * @property-read float|int $available
 * @property-read float|int $value
 * @property-read string|null $updated_at
 */
final class StockLevel extends Entity {}
