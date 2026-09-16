<?php

declare(strict_types=1);

namespace BillTo\Resources;

use BillTo\Entities\Entity;
use BillTo\Http\ApiResponse;
use BillTo\Http\Transport;
use BillTo\Page;
use BillTo\Paginator;

/**
 * Base class of every resource group (invoices, orders, ...).
 */
abstract class Resource
{
    public function __construct(protected readonly Transport $transport) {}

    /**
     * @template T of Entity
     *
     * @param  class-string<T>  $entity
     * @return T
     */
    protected function entity(ApiResponse $response, string $entity): Entity
    {
        return new $entity($response->dataObject());
    }

    /**
     * @template T of Entity
     *
     * @param  class-string<T>  $entity
     * @return list<T>
     */
    protected function entities(ApiResponse $response, string $entity): array
    {
        return array_map(static fn (array $row) => new $entity($row), $response->dataList());
    }

    /**
     * @template T of Entity
     *
     * @param  class-string<T>  $entity
     * @param  array<string, mixed>  $query
     * @return Page<T>
     */
    protected function page(string $path, array $query, string $entity): Page
    {
        return Page::fromResponse($this->transport->get($path, $query), $entity);
    }

    /**
     * @template T of Entity
     *
     * @param  class-string<T>  $entity
     * @param  array<string, mixed>  $filters
     * @return Paginator<T>
     */
    protected function paginate(string $path, array $filters, string $entity, int $perPage): Paginator
    {
        return new Paginator(fn (int $page): Page => $this->page($path, $filters + ['page' => $page, 'per_page' => $perPage], $entity));
    }

    /**
     * Drop null values so optional arguments are not sent as `null` to the API.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected static function compact(array $payload): array
    {
        return array_filter($payload, static fn ($value) => $value !== null);
    }

    protected static function dateString(\DateTimeInterface|string|null $value): ?string
    {
        return $value instanceof \DateTimeInterface ? $value->format('Y-m-d') : $value;
    }

    protected static function enumValue(\BackedEnum|string|null $value): ?string
    {
        return $value instanceof \BackedEnum ? (string) $value->value : $value;
    }
}
