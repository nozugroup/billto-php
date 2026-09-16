<?php

declare(strict_types=1);

namespace BillTo\Enums;

enum OrderStatus: string
{
    case Draft = 'draft';
    case Confirmed = 'confirmed';
    case Closed = 'closed';
    case Cancelled = 'cancelled';
}
