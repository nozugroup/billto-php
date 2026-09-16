<?php

declare(strict_types=1);

namespace BillTo\Exceptions;

/**
 * 409 - the resource state does not allow the operation: order already invoiced, invoice
 * currently being submitted to KSeF, document inside a multi-level approval workflow,
 * or a concurrent request with the same Idempotency-Key.
 */
final class ConflictException extends ApiException
{
    /**
     * Extra payload from the response (e.g. the KSeF status snapshot on a 409 during submission).
     *
     * @return array<string, mixed>|null
     */
    public function data(): ?array
    {
        $data = $this->get('data');

        return is_array($data) ? $data : null;
    }
}
