<?php

declare(strict_types=1);

namespace BillTo\Enums;

/**
 * Tax status of a party (`tax_type` of an invoice buyer, `tax_registration_type` of a contractor).
 */
enum TaxType: string
{
    /** Domestic taxpayer (NIP). */
    case Local = 'local';

    /** EU VAT taxpayer. */
    case EU = 'eu';

    /** Entity outside the EU. */
    case NonEU = 'noneu';

    /** No tax number (private individual). */
    case None = 'none';
}
