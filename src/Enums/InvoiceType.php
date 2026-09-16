<?php

declare(strict_types=1);

namespace BillTo\Enums;

/** Sales document type. */
enum InvoiceType: string
{
    /** VAT invoice. */
    case VAT = 'VAT';

    /** Correction invoice. */
    case KOR = 'KOR';

    /** Advance (prepayment) invoice. */
    case ZAL = 'ZAL';

    /** Final settlement invoice for advances. */
    case ROZ = 'ROZ';

    /** Correction of an advance invoice. */
    case KOR_ZAL = 'KOR_ZAL';

    /** Correction of a final settlement invoice. */
    case KOR_ROZ = 'KOR_ROZ';

    /** OSS invoice (B2C to the EU). */
    case OSS = 'OSS';

    /** Correction of an OSS invoice. */
    case KOR_OSS = 'KOR_OSS';

    public function isCorrection(): bool
    {
        return str_starts_with($this->value, 'KOR');
    }
}
