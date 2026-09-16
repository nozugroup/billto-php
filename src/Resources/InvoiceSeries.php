<?php

declare(strict_types=1);

namespace BillTo\Resources;

use BillTo\Entities\InvoiceSerie;
use BillTo\Enums\InvoiceType;

/**
 * Invoice numbering series: `/invoice-series`.
 */
final class InvoiceSeries extends Resource
{
    /**
     * Active series, optionally filtered by document type.
     *
     * @return list<InvoiceSerie>
     */
    public function list(InvoiceType|string|null $type = null): array
    {
        return $this->entities($this->transport->get('invoice-series', self::compact(['type' => self::enumValue($type)])), InvoiceSerie::class);
    }

    /** The default series for a document type (VAT by default), or null when none is marked default. */
    public function default(InvoiceType|string $type = InvoiceType::VAT): ?InvoiceSerie
    {
        foreach ($this->list($type) as $serie) {
            if ($serie->is_default) {
                return $serie;
            }
        }

        return null;
    }
}
