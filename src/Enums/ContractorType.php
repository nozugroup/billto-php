<?php

declare(strict_types=1);

namespace BillTo\Enums;

enum ContractorType: string
{
    /** The team's own company data (a single record managed in team settings). */
    case Own = 'own';

    /** External contractor. */
    case External = 'external';
}
