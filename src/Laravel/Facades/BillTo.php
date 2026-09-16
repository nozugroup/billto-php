<?php

declare(strict_types=1);

namespace BillTo\Laravel\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \BillTo\Resources\Invoices invoices()
 * @method static \BillTo\Resources\Orders orders()
 * @method static \BillTo\Resources\Contractors contractors()
 * @method static \BillTo\Resources\Products products()
 * @method static \BillTo\Resources\PriceGroups priceGroups()
 * @method static \BillTo\Resources\IncomingInvoices incomingInvoices()
 * @method static \BillTo\Resources\Warehouse warehouse()
 * @method static \BillTo\Resources\Reports reports()
 * @method static \BillTo\Resources\InvoiceSeries invoiceSeries()
 * @method static \BillTo\Resources\BankAccounts bankAccounts()
 * @method static \BillTo\Resources\ExchangeRates exchangeRates()
 * @method static \BillTo\Config config()
 *
 * @see \BillTo\BillTo
 */
final class BillTo extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \BillTo\BillTo::class;
    }
}
