<?php

declare(strict_types=1);

namespace BillTo\Entities;

/**
 * Price group (customer price list).
 *
 * @property-read string $id
 * @property-read string $name
 * @property-read string $pricing fixed|markup
 * @property-read float|int|null $markup_percent
 * @property-read float|int|null $margin_percent
 */
final class PriceGroup extends Entity {}
