<?php

declare(strict_types=1);

namespace BillTo\Resources;

use BillTo\Entities\PriceGroup;

/**
 * Price groups (customer price lists): `/price-groups`.
 */
final class PriceGroups extends Resource
{
    /** @return list<PriceGroup> */
    public function list(): array
    {
        return $this->entities($this->transport->get('price-groups'), PriceGroup::class);
    }
}
