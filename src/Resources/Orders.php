<?php

declare(strict_types=1);

namespace BillTo\Resources;

use BillTo\Entities\Invoice;
use BillTo\Entities\Order;
use BillTo\Entities\OrderActionResult;
use BillTo\Http\ApiResponse;
use BillTo\Http\BinaryFile;
use BillTo\Http\IdempotencyKey;
use BillTo\Page;
use BillTo\Paginator;

/**
 * Sales orders and the pay-then-invoice flow: `/orders`.
 */
final class Orders extends Resource
{
    /**
     * List regular orders (correcting orders are reachable via their parent or {@see get()}).
     *
     * Filters: `status`, `search` (order number).
     *
     * @param  array<string, mixed>  $filters
     * @return Page<Order>
     */
    public function list(array $filters = [], int $page = 1, int $perPage = 15): Page
    {
        return $this->page('orders', $filters + ['page' => $page, 'per_page' => $perPage], Order::class);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Paginator<Order>
     */
    public function all(array $filters = [], int $perPage = 100): Paginator
    {
        return $this->paginate('orders', $filters, Order::class, $perPage);
    }

    public function get(string $orderId): Order
    {
        return $this->entity($this->transport->get("orders/{$orderId}"), Order::class);
    }

    /**
     * Create an order. Required: `items[]` (`name`, `quantity`, `unit_price`, `vat_type`);
     * buyer via `buyer_contractor_id` or `buyer`. Optional: `status` (draft|confirmed),
     * `send_confirmation`, `external_id`, `source`, `currency`, `order_date`, `notes`.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?string $idempotencyKey = null): Order
    {
        return $this->entity($this->transport->post('orders', $data, $idempotencyKey), Order::class);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(string $orderId, array $data, ?string $idempotencyKey = null): Order
    {
        return $this->entity($this->transport->put("orders/{$orderId}", $data, $idempotencyKey), Order::class);
    }

    public function delete(string $orderId): void
    {
        $this->transport->delete("orders/{$orderId}");
    }

    /** Confirm a draft order (assigns the order number). */
    public function confirm(string $orderId, ?string $idempotencyKey = null): Order
    {
        return $this->entity($this->transport->post("orders/{$orderId}/confirm", null, $idempotencyKey), Order::class);
    }

    /** Close an open order. `meta.remaining_gross` holds the amount left uninvoiced. */
    public function close(string $orderId, ?string $idempotencyKey = null): OrderActionResult
    {
        return $this->actionResult($this->transport->post("orders/{$orderId}/close", null, $idempotencyKey));
    }

    /** Cancel an order. `meta.invoices_requiring_correction` lists invoices that still need a correction. */
    public function cancel(string $orderId, ?string $idempotencyKey = null): OrderActionResult
    {
        return $this->actionResult($this->transport->post("orders/{$orderId}/cancel", null, $idempotencyKey));
    }

    /** E-mail the order confirmation (PDF). Defaults to the buyer's e-mail. */
    public function sendConfirmation(string $orderId, ?string $email = null, ?string $idempotencyKey = null): Order
    {
        return $this->entity(
            $this->transport->post("orders/{$orderId}/send-confirmation", self::compact(['email' => $email]), $idempotencyKey),
            Order::class,
        );
    }

    public function pdf(string $orderId): BinaryFile
    {
        return $this->transport->download("orders/{$orderId}/pdf", [], "{$orderId}.pdf");
    }

    /**
     * Issue an advance (ZAL) invoice for a paid amount / installment.
     *
     * @param  array{amount?: float|string, installment_index?: int, series_id?: string}  $options
     */
    public function issueAdvance(string $orderId, array $options = [], ?string $idempotencyKey = null): Invoice
    {
        return $this->entity($this->transport->post("orders/{$orderId}/issue-advance", $options, $idempotencyKey), Invoice::class);
    }

    /**
     * Order paid in full - issue the VAT invoice from the default VAT series (201).
     * Safe to retry with the same idempotency key; a non-keyed retry on an already
     * invoiced order returns 409. Use {@see IdempotencyKey::fromOperation()}
     * with the payment webhook id.
     *
     * @param  bool  $sendEmail  false = BillTo does not e-mail the invoice (deliver the PDF yourself, or call
     *                           {@see Invoices::sendEmail()} later). Requires BillTo API from 2026-09-14.
     * @param  string|null  $seriesId  Active series of the team (matching the document type) to number the invoice from; null = the default series.
     * @param  string|null  $invoiceType  `vat` (default) or `oss` - OSS invoice for an EU consumer at the consumption country's rate.
     * @param  string|null  $ossVatType  Consumption-country rate for OSS (e.g. "19", "13.5"); null = the standard rate of the buyer's country.
     * @param  bool  $markPaid  false = issue the invoice unpaid (cash on delivery); record the payment later with {@see Invoices::markPaid()}.
     */
    public function markPaid(
        string $orderId,
        ?string $idempotencyKey = null,
        bool $sendEmail = true,
        ?string $seriesId = null,
        ?string $invoiceType = null,
        ?string $ossVatType = null,
        bool $markPaid = true,
    ): Invoice {
        $payload = self::compact([
            'send_email' => $sendEmail ? null : false,
            'mark_paid' => $markPaid ? null : false,
            'series_id' => $seriesId,
            'invoice_type' => $invoiceType,
            'oss_vat_type' => $ossVatType,
        ]);

        return $this->entity($this->transport->post("orders/{$orderId}/mark-paid", $payload ?: null, $idempotencyKey), Invoice::class);
    }

    /**
     * Step 1 of 2: create a correcting order for given invoice lines (return / post-sale discount).
     * `lines[]` = `{line_number, quantity?, after_unit_price?}`. Returns the correcting order;
     * `meta.corrected_invoice_id` (VAT path) or `meta.overpayment_gross` (advance path).
     * Issue the KOR with {@see issueKor()} on the returned correcting order.
     *
     * @param  list<array{line_number: int, quantity?: float|string|null, after_unit_price?: float|string|null}>  $lines
     */
    public function issueCorrection(string $orderId, array $lines, ?string $reason = null, ?string $idempotencyKey = null): OrderActionResult
    {
        $payload = self::compact(['lines' => $lines, 'reason' => $reason]);

        return $this->actionResult($this->transport->post("orders/{$orderId}/issue-correction", $payload, $idempotencyKey));
    }

    /**
     * Step 2 of 2: issue the correction invoice (KOR / KOR_ZAL) for a correcting order (201).
     *
     * @param  bool  $sendEmail  false = BillTo does not e-mail the correction to the buyer.
     */
    public function issueKor(string $correctingOrderId, ?string $seriesId = null, ?string $idempotencyKey = null, bool $sendEmail = true): Invoice
    {
        $payload = self::compact(['series_id' => $seriesId, 'send_email' => $sendEmail ? null : false]);

        return $this->entity(
            $this->transport->post("orders/{$correctingOrderId}/issue-kor", $payload, $idempotencyKey),
            Invoice::class,
        );
    }

    private function actionResult(ApiResponse $response): OrderActionResult
    {
        return new OrderActionResult(new Order($response->dataObject()), $response->meta());
    }
}
