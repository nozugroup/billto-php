<?php

declare(strict_types=1);

namespace BillTo\Entities;

/**
 * Catalog product.
 *
 * @property-read string $id
 * @property-read string $kind goods|service
 * @property-read string $name
 * @property-read string|null $description
 * @property-read string|null $units
 * @property-read string|null $sku
 * @property-read string|null $ean
 * @property-read string|null $unit_price
 * @property-read string|null $unit_price_gross
 * @property-read string|null $vat_type
 * @property-read string|null $pkwiu
 * @property-read bool $track_stock
 * @property-read string|null $low_stock_threshold
 * @property-read bool $is_active
 * @property-read list<array{id: string, unit: string, quantity: string|float}> $packagings
 * @property-read string $created_at
 * @property-read string $updated_at
 */
final class Product extends Entity
{
    public function isService(): bool
    {
        return $this->kind === 'service';
    }
}
