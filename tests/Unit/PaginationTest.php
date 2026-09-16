<?php

declare(strict_types=1);

use BillTo\Entities\Invoice;
use BillTo\Entities\StockLevel;

it('parses a Laravel-style page', function () {
    [$billto, $http] = fakeBillTo();
    $http->queue(jsonResponse(200, paginated([['id' => 'a'], ['id' => 'b']], 1, 3, 41, 2)));

    $page = $billto->invoices()->list(['status' => 'issued'], 1, 2);

    expect($page)->toHaveCount(2)
        ->and($page->currentPage)->toBe(1)
        ->and($page->lastPage)->toBe(3)
        ->and($page->total)->toBe(41)
        ->and($page->perPage)->toBe(2)
        ->and($page->hasMorePages())->toBeTrue()
        ->and($page->nextPage())->toBe(2)
        ->and($page->first())->toBeInstanceOf(Invoice::class)
        ->and($page->first()?->id)->toBe('a');
});

it('iterates lazily across all pages', function () {
    [$billto, $http] = fakeBillTo();
    $http->queue(
        jsonResponse(200, paginated([['id' => '1'], ['id' => '2']], 1, 3)),
        jsonResponse(200, paginated([['id' => '3'], ['id' => '4']], 2, 3)),
        jsonResponse(200, paginated([['id' => '5']], 3, 3)),
    );

    $ids = [];

    foreach ($billto->invoices()->all(['status' => 'issued'], 2) as $invoice) {
        $ids[] = $invoice->id;

        // Page 2 must not be requested before the iterator finishes page 1.
        if ($invoice->id === '1') {
            expect($http->requests)->toHaveCount(1);
        }
    }

    expect($ids)->toBe(['1', '2', '3', '4', '5'])
        ->and($http->requests)->toHaveCount(3)
        ->and($http->requests[1]->getUri()->getQuery())->toContain('page=2')
        ->and($http->requests[1]->getUri()->getQuery())->toContain('status=issued')
        ->and($http->requests[2]->getUri()->getQuery())->toContain('per_page=2');
});

it('collects all items and iterates pages', function () {
    [$billto, $http] = fakeBillTo();
    $http->queue(
        jsonResponse(200, paginated([['id' => '1']], 1, 2)),
        jsonResponse(200, paginated([['id' => '2']], 2, 2)),
        jsonResponse(200, paginated([['id' => '1']], 1, 2)),
        jsonResponse(200, paginated([['id' => '2']], 2, 2)),
    );

    $all = $billto->contractors()->all()->collect();
    expect($all)->toHaveCount(2);

    $pages = iterator_to_array($billto->contractors()->all()->pages(), false);
    expect($pages)->toHaveCount(2)->and($pages[1]->currentPage)->toBe(2);
});

it('handles the warehouse meta shape without per_page', function () {
    [$billto, $http] = fakeBillTo();
    $http->queue(jsonResponse(200, [
        'data' => [['id' => 's1', 'quantity' => 10.0, 'reserved' => 2.0, 'available' => 8.0]],
        'meta' => ['current_page' => 1, 'last_page' => 1, 'total' => 1],
    ]));

    $page = $billto->warehouse()->stocks(['product_id' => 'p-1']);

    expect($page->perPage)->toBeNull()
        ->and($page->hasMorePages())->toBeFalse()
        ->and($page->first())->toBeInstanceOf(StockLevel::class)
        ->and($page->first()?->amount('available'))->toBe(8.0);
});

it('handles an empty page', function () {
    [$billto, $http] = fakeBillTo();
    $http->queue(jsonResponse(200, paginated([], 1, 1, 0)));

    $page = $billto->orders()->list();

    expect($page->isEmpty())->toBeTrue()->and($page->first())->toBeNull()->and($page->toArray())->toBe([]);
});
