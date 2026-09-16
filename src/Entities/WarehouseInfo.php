<?php

declare(strict_types=1);

namespace BillTo\Entities;

/**
 * Warehouse.
 *
 * @property-read string $id
 * @property-read string $name
 * @property-read string|null $symbol
 * @property-read bool $is_default
 * @property-read bool $is_active
 */
final class WarehouseInfo extends Entity {}
