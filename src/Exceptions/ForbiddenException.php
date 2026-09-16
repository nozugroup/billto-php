<?php

declare(strict_types=1);

namespace BillTo\Exceptions;

/**
 * 403 - the token lacks the required scope, the plan does not include the module
 * (e.g. warehouse), the account is blocked by a NIP conflict (`code` = nip_conflict),
 * or the resource is managed automatically and cannot be modified.
 */
final class ForbiddenException extends ApiException
{
    public function isInsufficientScope(): bool
    {
        return str_contains(strtolower($this->getMessage()), 'scope');
    }

    public function isNipConflict(): bool
    {
        return $this->get('code') === 'nip_conflict';
    }
}
