<?php

declare(strict_types=1);

namespace BillTo\Resources;

use BillTo\Entities\Contractor;
use BillTo\Entities\ContractorInsights;
use BillTo\Page;
use BillTo\Paginator;

/**
 * Contractors: `/contractors`.
 */
final class Contractors extends Resource
{
    /**
     * Filters: `type` (own|external), `search` (name or tax number), `include` = `score`.
     *
     * @param  array<string, mixed>  $filters
     * @return Page<Contractor>
     */
    public function list(array $filters = [], int $page = 1, int $perPage = 15): Page
    {
        return $this->page('contractors', $filters + ['page' => $page, 'per_page' => $perPage], Contractor::class);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Paginator<Contractor>
     */
    public function all(array $filters = [], int $perPage = 100): Paginator
    {
        return $this->paginate('contractors', $filters, Contractor::class, $perPage);
    }

    public function get(string $contractorId): Contractor
    {
        return $this->entity($this->transport->get("contractors/{$contractorId}"), Contractor::class);
    }

    /**
     * First contractor matching a tax number (NIP), or null. Uses the `search` filter;
     * verify `tax_number` on the result for an exact match.
     */
    public function findByTaxNumber(string $taxNumber): ?Contractor
    {
        $normalized = preg_replace('/\s|-/', '', $taxNumber) ?? $taxNumber;

        foreach ($this->list(['search' => $normalized], 1, 50) as $contractor) {
            if (($contractor->tax_number ?? null) === $normalized) {
                return $contractor;
            }
        }

        return null;
    }

    /**
     * Create a contractor. Required: `name`. Optional: `email`, `phone`, `tax_registration_type`,
     * `tax_number`, `tax_country_code`, `client_number`, `price_group_id`,
     * `addresses[]` (`type` registered|correspondence, `country_code`, `postal_code`, `locality`,
     * `street`, `building_number`, `unit_number`).
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?string $idempotencyKey = null): Contractor
    {
        return $this->entity($this->transport->post('contractors', $data, $idempotencyKey), Contractor::class);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(string $contractorId, array $data, ?string $idempotencyKey = null): Contractor
    {
        return $this->entity($this->transport->put("contractors/{$contractorId}", $data, $idempotencyKey), Contractor::class);
    }

    public function delete(string $contractorId): void
    {
        $this->transport->delete("contractors/{$contractorId}");
    }

    /** 12-month sales/purchases, open and overdue receivables, 0-100 payment score. */
    public function insights(string $contractorId): ContractorInsights
    {
        return $this->entity($this->transport->get("contractors/{$contractorId}/insights"), ContractorInsights::class);
    }
}
