<?php

declare(strict_types=1);

namespace BillTo\Resources;

use BillTo\Entities\StockLevel;
use BillTo\Entities\StockMovement;
use BillTo\Entities\StockMovementReceipt;
use BillTo\Entities\WarehouseInfo;
use BillTo\Enums\StockMovementType;
use BillTo\Exceptions\ValidationException;
use BillTo\Page;
use BillTo\Paginator;

/**
 * Warehouse module: `/warehouse/*`. Every call returns 403 when the plan lacks the module.
 */
final class Warehouse extends Resource
{
    /** @return list<WarehouseInfo> */
    public function warehouses(): array
    {
        return $this->entities($this->transport->get('warehouse/warehouses'), WarehouseInfo::class);
    }

    /**
     * Stock rows (warehouse x product x location). Filters: `product_id`, `warehouse_id`,
     * `updated_since` (ISO 8601) for incremental sync.
     *
     * @param  array<string, mixed>  $filters
     * @return Page<StockLevel>
     */
    public function stocks(array $filters = [], int $page = 1, int $perPage = 15): Page
    {
        return $this->page('warehouse/stocks', $filters + ['page' => $page, 'per_page' => $perPage], StockLevel::class);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Paginator<StockLevel>
     */
    public function allStocks(array $filters = [], int $perPage = 100): Paginator
    {
        return $this->paginate('warehouse/stocks', $filters, StockLevel::class, $perPage);
    }

    /**
     * Movement ledger, newest first. Filters: `product_id`, `type`, `updated_since` (created_at).
     *
     * @param  array<string, mixed>  $filters
     * @return Page<StockMovement>
     */
    public function movements(array $filters = [], int $page = 1, int $perPage = 15): Page
    {
        return $this->page('warehouse/movements', $filters + ['page' => $page, 'per_page' => $perPage], StockMovement::class);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Paginator<StockMovement>
     */
    public function allMovements(array $filters = [], int $perPage = 100): Paginator
    {
        return $this->paginate('warehouse/movements', $filters, StockMovement::class, $perPage);
    }

    /**
     * Book a PZ/WZ/PW/RW movement through the stock engine.
     *
     * Identify the product with exactly one of `product_id`, `sku`, `ean`. Pass `external_id`
     * (your operation id) to make the call idempotent on the API side - a retry returns the
     * original movement with `replayed = true`.
     *
     * @param  array{product_id?: string, sku?: string, ean?: string, warehouse_id?: string, location?: string, unit_cost?: float|string, note?: string, date?: string, external_id?: string}  $options
     *
     * @throws ValidationException insufficient stock, closed period, service product, ambiguous code
     */
    public function move(StockMovementType|string $type, float|string $quantity, array $options = [], ?string $idempotencyKey = null): StockMovementReceipt
    {
        $payload = ['type' => self::enumValue($type), 'quantity' => $quantity] + $options;

        return $this->entity($this->transport->post('warehouse/movements', $payload, $idempotencyKey), StockMovementReceipt::class);
    }

    /**
     * @param  array<string, mixed>  $options  See {@see move()}.
     */
    public function receive(float|string $quantity, array $options = [], ?string $idempotencyKey = null): StockMovementReceipt
    {
        return $this->move(StockMovementType::PZ, $quantity, $options, $idempotencyKey);
    }

    /**
     * @param  array<string, mixed>  $options  See {@see move()}.
     */
    public function issue(float|string $quantity, array $options = [], ?string $idempotencyKey = null): StockMovementReceipt
    {
        return $this->move(StockMovementType::WZ, $quantity, $options, $idempotencyKey);
    }
}
