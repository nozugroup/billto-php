<?php

declare(strict_types=1);

namespace BillTo\Resources;

use BillTo\Entities\IncomingInvoice;
use BillTo\Entities\VoteResult;
use BillTo\Enums\IncomingInvoiceStatus;
use BillTo\Exceptions\ConflictException;
use BillTo\Exceptions\NotFoundException;
use BillTo\Exceptions\ValidationException;
use BillTo\Http\BinaryFile;
use BillTo\Page;
use BillTo\Paginator;

/**
 * Incoming (cost) invoices - the KSeF inbox: `/incoming-invoices`.
 */
final class IncomingInvoices extends Resource
{
    /**
     * Filters: `status` (pending|accepted|rejected), `updated_since` (ISO 8601, for incremental sync).
     *
     * @param  array<string, mixed>  $filters
     * @return Page<IncomingInvoice>
     */
    public function list(array $filters = [], int $page = 1, int $perPage = 50): Page
    {
        return $this->page('incoming-invoices', $filters + ['page' => $page, 'per_page' => $perPage], IncomingInvoice::class);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Paginator<IncomingInvoice>
     */
    public function all(array $filters = [], int $perPage = 200): Paginator
    {
        return $this->paginate('incoming-invoices', $filters, IncomingInvoice::class, $perPage);
    }

    /**
     * Everything changed since a point in time - the intended way to sync incrementally.
     *
     * @return Paginator<IncomingInvoice>
     */
    public function updatedSince(\DateTimeInterface|string $since, IncomingInvoiceStatus|string|null $status = null, int $perPage = 200): Paginator
    {
        $filters = self::compact([
            'updated_since' => $since instanceof \DateTimeInterface ? $since->format(DATE_ATOM) : $since,
            'status' => self::enumValue($status),
        ]);

        return $this->all($filters, $perPage);
    }

    public function get(string $incomingInvoiceId): IncomingInvoice
    {
        return $this->entity($this->transport->get("incoming-invoices/{$incomingInvoiceId}"), IncomingInvoice::class);
    }

    /**
     * Original FA(3) XML fetched from KSeF.
     *
     * @throws NotFoundException XML not downloaded (yet)
     */
    public function xml(string $incomingInvoiceId): BinaryFile
    {
        return $this->transport->download("incoming-invoices/{$incomingInvoiceId}/xml", [], "{$incomingInvoiceId}.xml");
    }

    /**
     * Accept a pending invoice.
     *
     * @throws ValidationException not pending, or required fields missing
     * @throws ConflictException inside a multi-level approval workflow (decide in the app)
     */
    public function accept(string $incomingInvoiceId, ?string $idempotencyKey = null): IncomingInvoice
    {
        return $this->entity($this->transport->post("incoming-invoices/{$incomingInvoiceId}/accept", null, $idempotencyKey), IncomingInvoice::class);
    }

    /**
     * Reject a pending invoice with an optional reason.
     */
    public function reject(string $incomingInvoiceId, ?string $reason = null, ?string $idempotencyKey = null): IncomingInvoice
    {
        return $this->entity(
            $this->transport->post("incoming-invoices/{$incomingInvoiceId}/reject", self::compact(['reason' => $reason]), $idempotencyKey),
            IncomingInvoice::class,
        );
    }

    /**
     * Record a vote cast in the accounting office panel (DocuFlow). Attributed to the team
     * member with the given e-mail; `applied` is false (202) when no member matched.
     *
     * @param  'approved'|'rejected'  $decision
     */
    public function vote(
        string $incomingInvoiceId,
        string $decision,
        string $email,
        ?string $name = null,
        \DateTimeInterface|string|null $decidedAt = null,
        ?string $ksefNumber = null,
        ?string $idempotencyKey = null,
    ): VoteResult {
        $payload = self::compact([
            'decision' => $decision,
            'email' => $email,
            'name' => $name,
            'decided_at' => $decidedAt instanceof \DateTimeInterface ? $decidedAt->format(DATE_ATOM) : $decidedAt,
            'ksef_number' => $ksefNumber,
        ]);

        $response = $this->transport->post("incoming-invoices/{$incomingInvoiceId}/vote", $payload, $idempotencyKey);
        $json = $response->json() ?? [];

        return new VoteResult(
            (bool) ($json['applied'] ?? false),
            isset($json['reason']) && is_string($json['reason']) ? $json['reason'] : null,
            new IncomingInvoice($response->dataObject()),
        );
    }
}
