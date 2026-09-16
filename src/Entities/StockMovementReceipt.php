<?php

declare(strict_types=1);

namespace BillTo\Entities;

/**
 * Confirmation of a booked stock movement.
 *
 * @property-read string $id
 * @property-read string $type
 * @property-read float|int|null $quantity_after
 * @property-read bool $replayed true = a retried request with the same `external_id`; the movement was not booked twice
 */
final class StockMovementReceipt extends Entity {}
