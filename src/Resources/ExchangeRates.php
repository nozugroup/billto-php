<?php

declare(strict_types=1);

namespace BillTo\Resources;

use BillTo\Entities\ExchangeRate;

/**
 * NBP exchange rates: `/exchange-rates` (max 100 rows, newest first).
 */
final class ExchangeRates extends Resource
{
    /**
     * @return list<ExchangeRate>
     */
    public function list(
        ?string $currency = null,
        \DateTimeInterface|string|null $date = null,
        \DateTimeInterface|string|null $dateFrom = null,
        \DateTimeInterface|string|null $dateTo = null,
    ): array {
        $query = self::compact([
            'currency' => $currency !== null ? strtoupper($currency) : null,
            'date' => self::dateString($date),
            'date_from' => self::dateString($dateFrom),
            'date_to' => self::dateString($dateTo),
        ]);

        return $this->entities($this->transport->get('exchange-rates', $query), ExchangeRate::class);
    }

    /** Rate of a currency on a given day (or the latest one), or null when NBP has none. */
    public function rate(string $currency, \DateTimeInterface|string|null $date = null): ?ExchangeRate
    {
        return $this->list($currency, $date)[0] ?? null;
    }
}
