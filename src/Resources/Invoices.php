<?php

declare(strict_types=1);

namespace BillTo\Resources;

use BillTo\Entities\EmailDelivery;
use BillTo\Entities\Invoice;
use BillTo\Entities\KsefStatus;
use BillTo\Entities\PublicLink;
use BillTo\Enums\PaymentMethod;
use BillTo\Exceptions\ConflictException;
use BillTo\Exceptions\NotFoundException;
use BillTo\Exceptions\ValidationException;
use BillTo\Http\BinaryFile;
use BillTo\Page;
use BillTo\Paginator;

/**
 * Sales invoices: `/invoices`.
 */
final class Invoices extends Resource
{
    /**
     * List invoices (one page).
     *
     * Filters: `status`, `type`, `date_from`, `date_to`, `buyer_id`, `search` (invoice number).
     *
     * @param  array<string, mixed>  $filters
     * @return Page<Invoice>
     */
    public function list(array $filters = [], int $page = 1, int $perPage = 15): Page
    {
        return $this->page('invoices', $filters + ['page' => $page, 'per_page' => $perPage], Invoice::class);
    }

    /**
     * Iterate over every invoice matching the filters, fetching pages lazily.
     *
     * @param  array<string, mixed>  $filters
     * @return Paginator<Invoice>
     */
    public function all(array $filters = [], int $perPage = 100): Paginator
    {
        return $this->paginate('invoices', $filters, Invoice::class, $perPage);
    }

    public function get(string $invoiceId): Invoice
    {
        return $this->entity($this->transport->get("invoices/{$invoiceId}"), Invoice::class);
    }

    /**
     * Create an invoice (draft by default). Pass `issue => true` to issue it right away,
     * optionally with `mark_paid`, `paid_amount`, `paid_date`, `payment_method`.
     *
     * Buyer: either `buyer_contractor_id` or a `buyer` object. Required: `type`, `issue_date`,
     * `payment_method`, `currency`, `amount_entry_mode` (net|gross), `items[]` with
     * `name`, `quantity`, `vat_type` and `unit_price` or `unit_price_gross`.
     *
     * @param  array<string, mixed>  $data
     * @param  string|null  $idempotencyKey  Your own key (e.g. order id) to make retries safe; auto-generated when omitted.
     */
    public function create(array $data, ?string $idempotencyKey = null): Invoice
    {
        return $this->entity($this->transport->post('invoices', $data, $idempotencyKey), Invoice::class);
    }

    /**
     * Replace all fields of a draft invoice. Issued invoices return 403.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(string $invoiceId, array $data, ?string $idempotencyKey = null): Invoice
    {
        return $this->entity($this->transport->put("invoices/{$invoiceId}", $data, $idempotencyKey), Invoice::class);
    }

    /** Permanently delete a draft invoice. */
    public function delete(string $invoiceId): void
    {
        $this->transport->delete("invoices/{$invoiceId}");
    }

    /** Issue a draft: assigns a number from its series. The invoice becomes immutable. */
    public function issue(string $invoiceId, ?string $idempotencyKey = null): Invoice
    {
        return $this->entity($this->transport->post("invoices/{$invoiceId}/issue", null, $idempotencyKey), Invoice::class);
    }

    /**
     * Submit the invoice to KSeF (interactive session using the team's login certificate).
     * Returns the current KSeF snapshot (202); the KSeF number is assigned asynchronously -
     * poll {@see self::ksefStatus()}. Requires the `ksef:send` scope.
     *
     * @throws ConflictException already being submitted
     * @throws ValidationException no certificate configured or the submission was rejected
     */
    public function sendToKsef(string $invoiceId, ?string $idempotencyKey = null): KsefStatus
    {
        return $this->entity($this->transport->post("invoices/{$invoiceId}/ksef", null, $idempotencyKey), KsefStatus::class);
    }

    /** Current KSeF status: number, UPO, unresolved errors. */
    public function ksefStatus(string $invoiceId): KsefStatus
    {
        return $this->entity($this->transport->get("invoices/{$invoiceId}/ksef"), KsefStatus::class);
    }

    /**
     * Poll {@see self::ksefStatus()} until a KSeF number is assigned or an unresolved error appears.
     * Returns the final status; returns the last seen status when the timeout elapses.
     *
     * @param  int  $timeoutSeconds  Total time budget.
     * @param  int  $intervalSeconds  Delay between polls.
     * @param  callable(float): void|null  $sleeper  Sleep function (tests).
     */
    public function waitForKsef(string $invoiceId, int $timeoutSeconds = 120, int $intervalSeconds = 5, ?callable $sleeper = null): KsefStatus
    {
        $sleeper ??= static function (float $seconds): void {
            usleep((int) round($seconds * 1_000_000));
        };
        $deadline = microtime(true) + $timeoutSeconds;

        while (true) {
            $status = $this->ksefStatus($invoiceId);

            if ($status->isAssigned() || $status->hasErrors() || microtime(true) >= $deadline) {
                return $status;
            }

            $sleeper((float) $intervalSeconds);
        }
    }

    /** Download the invoice PDF. */
    public function pdf(string $invoiceId): BinaryFile
    {
        return $this->transport->download("invoices/{$invoiceId}/pdf", [], "{$invoiceId}.pdf");
    }

    /**
     * Download the KSeF FA(3) XML. Latest version by default; pass `$version` for a specific one.
     *
     * @throws NotFoundException no XML generated yet
     */
    public function xml(string $invoiceId, ?int $version = null): BinaryFile
    {
        return $this->transport->download("invoices/{$invoiceId}/xml", self::compact(['version' => $version]), "{$invoiceId}.xml");
    }

    /**
     * E-mail the invoice (PDF attached + public page link). Defaults to the buyer's e-mail.
     * Queued on the API side - returns once accepted (202).
     */
    public function sendEmail(string $invoiceId, ?string $email = null, ?string $idempotencyKey = null): EmailDelivery
    {
        return $this->entity(
            $this->transport->post("invoices/{$invoiceId}/send-email", self::compact(['email' => $email]), $idempotencyKey),
            EmailDelivery::class,
        );
    }

    /** Create (on first call) and return the public invoice page URL. */
    public function publicLink(string $invoiceId): PublicLink
    {
        return $this->entity($this->transport->post("invoices/{$invoiceId}/public-link"), PublicLink::class);
    }

    /**
     * Record a full or partial payment. `paid_amount` / `remaining_amount` recalculate automatically.
     *
     * @param  float|string  $amount  Major currency unit, e.g. 1230.00.
     */
    public function recordPayment(
        string $invoiceId,
        float|string $amount,
        \DateTimeInterface|string|null $paidAt = null,
        PaymentMethod|string|null $paymentMethod = null,
        ?string $note = null,
        ?string $idempotencyKey = null,
    ): Invoice {
        $payload = self::compact([
            'amount' => $amount,
            'paid_at' => self::dateString($paidAt),
            'payment_method' => self::enumValue($paymentMethod),
            'note' => $note,
        ]);

        return $this->entity($this->transport->post("invoices/{$invoiceId}/payments", $payload, $idempotencyKey), Invoice::class);
    }

    /** Mark the invoice fully paid (records the remaining balance). Idempotent on the API side. */
    public function markPaid(
        string $invoiceId,
        \DateTimeInterface|string|null $paidAt = null,
        PaymentMethod|string|null $paymentMethod = null,
        ?string $idempotencyKey = null,
    ): Invoice {
        $payload = self::compact([
            'paid_at' => self::dateString($paidAt),
            'payment_method' => self::enumValue($paymentMethod),
        ]);

        return $this->entity($this->transport->post("invoices/{$invoiceId}/mark-paid", $payload, $idempotencyKey), Invoice::class);
    }

    /** Remove a recorded payment and recalculate the paid state. */
    public function deletePayment(string $invoiceId, string $paymentId): void
    {
        $this->transport->delete("invoices/{$invoiceId}/payments/{$paymentId}");
    }
}
