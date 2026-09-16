<?php

declare(strict_types=1);

namespace BillTo\Entities;

/**
 * Stock movement from the ledger.
 *
 * @property-read string $id
 * @property-read string $type
 * @property-read string|null $movement_date
 * @property-read string $product_id
 * @property-read string|null $product
 * @property-read string|null $warehouse_id
 * @property-read string|null $location
 * @property-read float|int $quantity
 * @property-read float|int $quantity_after
 * @property-read float|int|null $unit_cost
 * @property-read string|null $note
 * @property-read string|null $created_at
 */
final class StockMovement extends Entity {}
