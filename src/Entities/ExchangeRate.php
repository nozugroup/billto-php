<?php

declare(strict_types=1);

namespace BillTo\Entities;

/**
 * NBP exchange rate.
 *
 * @property-read string $currency
 * @property-read string|float $rate
 * @property-read string $rate_date
 * @property-read string|null $source
 */
final class ExchangeRate extends Entity {}
