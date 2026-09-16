<?php

declare(strict_types=1);

namespace BillTo\Entities;

use BillTo\Enums\IncomingInvoiceStatus;

/**
 * Incoming (cost) invoice from the KSeF inbox, DocuFlow or manual upload.
 *
 * @property-read string $id
 * @property-read string $source ksef|docuflow|manual
 * @property-read string|null $duplicate_group_id
 * @property-read string|null $ksef_number
 * @property-read string|null $external_id
 * @property-read string|null $invoice_number
 * @property-read string|null $seller_nip
 * @property-read string|null $seller_name
 * @property-read string|null $issue_date
 * @property-read string|null $net_amount
 * @property-read string|null $vat_amount
 * @property-read string|null $gross_amount
 * @property-read string $currency
 * @property-read string $status pending|accepted|rejected
 * @property-read string|null $extraction_status
 * @property-read string|null $cost_category
 * @property-read string|null $project
 * @property-read string|null $notes
 * @property-read string|null $accepted_at
 * @property-read string|null $accepted_via
 * @property-read string|null $rejection_reason
 * @property-read bool $in_approval_flow
 * @property-read array{in_flow: bool, workflow_name: string|null, matched_conditions: mixed, current_level: int|null, levels_total: int|null}|null $approval
 * @property-read array{question: string, asked_at: string}|null $clarification_open
 * @property-read array<string, mixed>|null $duplicate_suspicion
 * @property-read string|null $ksef_acquired_at
 * @property-read string|null $paid_at
 * @property-read string|null $paid_amount
 * @property-read string|null $payment_due_date
 * @property-read string|null $seller_bank_account
 * @property-read bool $split_payment
 * @property-read bool $has_xml
 * @property-read bool $has_file
 * @property-read list<array<string, mixed>> $lines
 * @property-read string $created_at
 * @property-read string $updated_at
 */
final class IncomingInvoice extends Entity
{
    public function statusEnum(): ?IncomingInvoiceStatus
    {
        return IncomingInvoiceStatus::tryFrom((string) $this->status);
    }

    public function isPending(): bool
    {
        return $this->status === IncomingInvoiceStatus::Pending->value;
    }

    /** Document inside a multi-level approval workflow - accept()/reject() via the API returns 409. */
    public function isInApprovalFlow(): bool
    {
        return (bool) ($this->get('approval.in_flow') ?? $this->in_approval_flow);
    }
}
