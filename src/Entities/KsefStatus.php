<?php

declare(strict_types=1);

namespace BillTo\Entities;

/**
 * KSeF submission state of an invoice.
 *
 * @property-read bool $is_ksef
 * @property-read bool $sent
 * @property-read string|null $ksef_number
 * @property-read bool $ksef_offline
 * @property-read string|null $ksef_date
 * @property-read bool $has_unresolved_errors
 * @property-read list<array{status_code: string|int|null, status_description: string|null, ksef_error_at: string|null, resolved_at: string|null}> $errors
 * @property-read array{document_reference_number: string|null, sent_at: string|null, ksef_assigned_at: string|null}|null $upo
 */
final class KsefStatus extends Entity
{
    /** A KSeF number has been assigned - submission succeeded. */
    public function isAssigned(): bool
    {
        return is_string($this->ksef_number) && $this->ksef_number !== '';
    }

    /** Submission in progress: sent, no number yet and no unresolved errors. */
    public function isPending(): bool
    {
        return (bool) $this->sent && ! $this->isAssigned() && ! $this->has_unresolved_errors;
    }

    public function hasErrors(): bool
    {
        return (bool) $this->has_unresolved_errors;
    }
}
