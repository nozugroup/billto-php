<?php

declare(strict_types=1);

namespace BillTo\Entities;

/**
 * Outcome of a vote cast in the accounting office panel (DocuFlow).
 */
final class VoteResult
{
    public function __construct(
        public readonly bool $applied,
        public readonly ?string $reason,
        public readonly IncomingInvoice $invoice,
    ) {}
}
