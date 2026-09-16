<?php

declare(strict_types=1);

namespace BillTo\Enums;

enum PaymentMethod: string
{
    case Transfer = 'transfer';
    case Cash = 'cash';
    case Card = 'card';
    case Compensation = 'compensation';
    case Other = 'other';
}
