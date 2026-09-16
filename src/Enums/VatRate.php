<?php

declare(strict_types=1);

namespace BillTo\Enums;

/** Line VAT rate (`vat_type`). Values are exactly what the API expects. */
enum VatRate: string
{
    case Rate23 = '23';
    case Rate22 = '22';
    case Rate8 = '8';
    case Rate7 = '7';
    case Rate5 = '5';
    case Rate4 = '4';
    case Rate3 = '3';

    /** 0% domestic. */
    case ZeroDomestic = '0 KR';

    /** 0% intra-community supply of goods (WDT). */
    case ZeroWdt = '0 WDT';

    /** 0% export. */
    case ZeroExport = '0 EX';

    /** Exempt. */
    case Exempt = 'zw';

    /** Reverse charge. */
    case ReverseCharge = 'oo';

    /** Not subject to VAT (I). */
    case NotSubjectI = 'np I';

    /** Not subject to VAT (II). */
    case NotSubjectII = 'np II';

    /** Rate percentage, or null for exempt / reverse charge / not subject. */
    public function percent(): ?float
    {
        return match ($this) {
            self::Rate23 => 23.0,
            self::Rate22 => 22.0,
            self::Rate8 => 8.0,
            self::Rate7 => 7.0,
            self::Rate5 => 5.0,
            self::Rate4 => 4.0,
            self::Rate3 => 3.0,
            self::ZeroDomestic, self::ZeroWdt, self::ZeroExport => 0.0,
            default => null,
        };
    }
}
