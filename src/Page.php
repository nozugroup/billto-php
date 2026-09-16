<?php

declare(strict_types=1);

namespace BillTo;

use BillTo\Http\ApiResponse;

/**
 * A single page of results from a paginated endpoint.
 *
 * @template T of Entities\Entity
 *
 * @implements \IteratorAggregate<int, T>
 */
final class Page implements \Countable, \IteratorAggregate
{
    /**
     * @param  list<T>  $items
     */
    public function __construct(
        public readonly array $items,
        public readonly int $currentPage,
        public readonly int $lastPage,
        public readonly ?int $total,
        public readonly ?int $perPage,
    ) {}

    /**
     * @template E of Entities\Entity
     *
     * @param  class-string<E>  $entity
     * @return self<E>
     */
    public static function fromResponse(ApiResponse $response, string $entity): self
    {
        $meta = $response->meta();
        $items = array_map(static fn (array $row) => new $entity($row), $response->dataList());

        return new self(
            $items,
            (int) ($meta['current_page'] ?? 1),
            (int) ($meta['last_page'] ?? 1),
            isset($meta['total']) ? (int) $meta['total'] : null,
            isset($meta['per_page']) ? (int) $meta['per_page'] : null,
        );
    }

    public function hasMorePages(): bool
    {
        return $this->currentPage < $this->lastPage;
    }

    public function nextPage(): ?int
    {
        return $this->hasMorePages() ? $this->currentPage + 1 : null;
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    /** @return T|null */
    public function first(): ?Entities\Entity
    {
        return $this->items[0] ?? null;
    }

    public function count(): int
    {
        return count($this->items);
    }

    /** @return \ArrayIterator<int, T> */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->items);
    }

    /** @return list<array<string, mixed>> */
    public function toArray(): array
    {
        return array_map(static fn (Entities\Entity $item) => $item->toArray(), $this->items);
    }
}
