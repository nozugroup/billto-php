<?php

declare(strict_types=1);

namespace BillTo\Enums;

/** Stock movement type accepted by the booking endpoint. */
enum StockMovementType: string
{
    /** External receipt (PZ). */
    case PZ = 'pz';

    /** External issue (WZ). */
    case WZ = 'wz';

    /** Internal receipt (PW). */
    case PW = 'pw';

    /** Internal issue (RW). */
    case RW = 'rw';
}
