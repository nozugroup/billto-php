<?php

declare(strict_types=1);

namespace BillTo\Entities;

/**
 * Sales profitability report.
 *
 * @property-read array{from: string, to: string, group_by: string} $meta
 * @property-read array<string, mixed> $summary
 * @property-read list<array<string, mixed>> $groups
 * @property-read list<array<string, mixed>> $below_cost
 */
final class ProfitabilityReport extends Entity {}
