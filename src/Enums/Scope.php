<?php

declare(strict_types=1);

namespace BillTo\Enums;

/** API token scopes (BillTo: Settings -> API tokens). */
enum Scope: string
{
    case InvoicesRead = 'invoices:read';
    case InvoicesWrite = 'invoices:write';
    case OrdersRead = 'orders:read';
    case OrdersWrite = 'orders:write';
    case KsefSend = 'ksef:send';
    case ContractorsRead = 'contractors:read';
    case ContractorsWrite = 'contractors:write';
    case ProductsRead = 'products:read';
    case ProductsWrite = 'products:write';
    case IncomingRead = 'incoming:read';
    case IncomingWrite = 'incoming:write';
    case IncomingVote = 'incoming:vote';
    case WarehouseRead = 'warehouse:read';
    case WarehouseWrite = 'warehouse:write';
}
