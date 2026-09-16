<?php

declare(strict_types=1);

namespace BillTo;

/**
 * Lazy iteration over every page - the next page is fetched only once iteration reaches it.
 *
 * ```php
 * foreach ($billto->invoices()->all(['status' => 'issued']) as $invoice) { ... }
 * ```
 *
 * @template T of Entities\Entity
 *
 * @implements \IteratorAggregate<int, T>
 */
final class Paginator implements \IteratorAggregate
{
    /** @var callable(int): Page<T> */
    private $fetchPage;

    /**
     * @param  callable(int): Page<T>  $fetchPage
     */
    public function __construct(
        callable $fetchPage,
        private readonly int $startPage = 1,
    ) {
        $this->fetchPage = $fetchPage;
    }

    /** @return \Generator<int, T> */
    public function getIterator(): \Generator
    {
        foreach ($this->pages() as $page) {
            foreach ($page->items as $item) {
                yield $item;
            }
        }
    }

    /**
     * Lazy iterator over pages (not items).
     *
     * @return \Generator<int, Page<T>>
     */
    public function pages(): \Generator
    {
        $pageNumber = $this->startPage;

        do {
            /** @var Page<T> $page */
            $page = ($this->fetchPage)($pageNumber);

            yield $page;

            $pageNumber = $page->nextPage();
        } while ($pageNumber !== null);
    }

    /**
     * Load everything into an array. Mind memory on large data sets - iterate instead.
     *
     * @return list<T>
     */
    public function collect(): array
    {
        return iterator_to_array($this->getIterator(), false);
    }
}
