<?php

declare(strict_types=1);

namespace BillTo\Resources;

use BillTo\Entities\ProfitabilityReport;

/**
 * Reports: `/reports/*`.
 */
final class Reports extends Resource
{
    /**
     * Revenue, COGS and margin over issued sales invoices in a date range (current month by default),
     * grouped by `product` or `contractor`. Requires a plan with the warehouse module.
     *
     * @param  'product'|'contractor'|null  $groupBy
     */
    public function profitability(
        \DateTimeInterface|string|null $from = null,
        \DateTimeInterface|string|null $to = null,
        ?string $groupBy = null,
    ): ProfitabilityReport {
        $query = self::compact([
            'from' => self::dateString($from),
            'to' => self::dateString($to),
            'group_by' => $groupBy,
        ]);

        $response = $this->transport->get('reports/profitability', $query);

        return new ProfitabilityReport($response->json() ?? []);
    }
}
