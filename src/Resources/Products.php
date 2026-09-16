<?php

declare(strict_types=1);

namespace BillTo\Resources;

use BillTo\Entities\Product;
use BillTo\Entities\ProductPrice;
use BillTo\Page;
use BillTo\Paginator;

/**
 * Product catalog: `/products`.
 */
final class Products extends Resource
{
    /**
     * Filters: `search`, `kind` (goods|service), `active_only` (default true).
     *
     * @param  array<string, mixed>  $filters
     * @return Page<Product>
     */
    public function list(array $filters = [], int $page = 1, int $perPage = 15): Page
    {
        return $this->page('products', $filters + ['page' => $page, 'per_page' => $perPage], Product::class);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Paginator<Product>
     */
    public function all(array $filters = [], int $perPage = 100): Paginator
    {
        return $this->paginate('products', $filters, Product::class, $perPage);
    }

    public function get(string $productId): Product
    {
        return $this->entity($this->transport->get("products/{$productId}"), Product::class);
    }

    /**
     * Create a product. Required: `name`. Optional: `kind`, `description`, `units`, `sku`, `ean`,
     * `unit_price`, `unit_price_gross`, `vat_type`, `pkwiu`, `track_stock`, `low_stock_threshold`,
     * `is_active`, `packagings[]` (`unit`, `quantity`).
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, ?string $idempotencyKey = null): Product
    {
        return $this->entity($this->transport->post('products', $data, $idempotencyKey), Product::class);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(string $productId, array $data, ?string $idempotencyKey = null): Product
    {
        return $this->entity($this->transport->put("products/{$productId}", $data, $idempotencyKey), Product::class);
    }

    public function delete(string $productId): void
    {
        $this->transport->delete("products/{$productId}");
    }

    /**
     * Price BillTo would suggest for this product and buyer (price group, WDT 0% for EU buyers).
     * Pass a `contractorId` or a `priceGroupId`; neither = base price.
     */
    public function price(string $productId, ?string $contractorId = null, ?string $priceGroupId = null): ProductPrice
    {
        $query = self::compact(['contractor_id' => $contractorId, 'price_group_id' => $priceGroupId]);

        return $this->entity($this->transport->get("products/{$productId}/price", $query), ProductPrice::class);
    }
}
