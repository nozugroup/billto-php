<?php

declare(strict_types=1);

namespace BillTo\Enums;

enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Issued = 'issued';
    case KsefIssuedOffline = 'ksef_issued_offline';
    case KsefIssued = 'ksef_issued';
    case Cancelled = 'cancelled';

    public function isIssued(): bool
    {
        return $this !== self::Draft && $this !== self::Cancelled;
    }
}
