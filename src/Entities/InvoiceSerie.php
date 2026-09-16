<?php

declare(strict_types=1);

namespace BillTo\Entities;

/**
 * Invoice numbering series.
 *
 * @property-read string $id
 * @property-read string $type
 * @property-read string $code
 * @property-read string $name
 * @property-read string $pattern
 * @property-read bool $is_default
 * @property-read bool $is_active
 */
final class InvoiceSerie extends Entity {}
