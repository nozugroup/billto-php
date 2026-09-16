<?php

declare(strict_types=1);

namespace BillTo\Enums;

enum IncomingInvoiceStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
}
