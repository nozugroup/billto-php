<?php

declare(strict_types=1);

namespace BillTo\Entities;

/**
 * Payment indicators of a contractor.
 *
 * @property-read array{sales_12m_gross: mixed, sales_12m_count: int, open_amount: mixed, open_count: int, overdue_amount: mixed, overdue_count: int, oldest_overdue_days: int|null, purchases_12m_gross: mixed, purchases_12m_count: int, documents_count: int} $stats
 * @property-read array<string, mixed> $payment_score 0-100 rating with components; `score` is null when there is too little data.
 */
final class ContractorInsights extends Entity
{
    public function score(): ?int
    {
        $score = $this->get('payment_score.score');

        return is_numeric($score) ? (int) $score : null;
    }
}
