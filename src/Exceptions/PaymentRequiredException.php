<?php

declare(strict_types=1);

namespace BillTo\Exceptions;

/** 402 - the team has no active plan that allows this operation. */
final class PaymentRequiredException extends ApiException
{
    public function upgradeUrl(): ?string
    {
        $url = $this->get('upgrade_url');

        return is_string($url) ? $url : null;
    }
}
