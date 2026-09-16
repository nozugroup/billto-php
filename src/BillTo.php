<?php

declare(strict_types=1);

namespace BillTo;

use BillTo\Http\Transport;
use BillTo\Resources\BankAccounts;
use BillTo\Resources\Contractors;
use BillTo\Resources\ExchangeRates;
use BillTo\Resources\IncomingInvoices;
use BillTo\Resources\Invoices;
use BillTo\Resources\InvoiceSeries;
use BillTo\Resources\Orders;
use BillTo\Resources\PriceGroups;
use BillTo\Resources\Products;
use BillTo\Resources\Reports;
use BillTo\Resources\Warehouse;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * SDK entry point.
 *
 * ```php
 * $billto = BillTo::create('blto_...');
 * $invoice = $billto->invoices()->create([...]);
 * ```
 */
final class BillTo
{
    private ?Invoices $invoices = null;

    private ?Orders $orders = null;

    private ?Contractors $contractors = null;

    private ?Products $products = null;

    private ?PriceGroups $priceGroups = null;

    private ?IncomingInvoices $incomingInvoices = null;

    private ?Warehouse $warehouse = null;

    private ?Reports $reports = null;

    private ?InvoiceSeries $invoiceSeries = null;

    private ?BankAccounts $bankAccounts = null;

    private ?ExchangeRates $exchangeRates = null;

    public function __construct(
        private readonly Config $config,
        private readonly Transport $transport,
    ) {}

    /**
     * Create a client with a team API token (BillTo: Settings -> API tokens).
     *
     * The HTTP client and PSR-17 factories are auto-discovered (php-http/discovery),
     * so having e.g. guzzlehttp/guzzle installed is enough. They can also be passed explicitly.
     */
    public static function create(
        string $token,
        ?string $baseUrl = null,
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
    ): self {
        $config = new Config($token, $baseUrl ?? Config::DEFAULT_BASE_URL);

        return self::fromConfig($config, $httpClient, $requestFactory, $streamFactory);
    }

    /** Client pointed at the sandbox.billto.pl test environment. */
    public static function sandbox(string $token, ?ClientInterface $httpClient = null): self
    {
        return self::create($token, Config::SANDBOX_BASE_URL, $httpClient);
    }

    public static function fromConfig(
        Config $config,
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
    ): self {
        return new self($config, new Transport($config, $httpClient, $requestFactory, $streamFactory));
    }

    public function config(): Config
    {
        return $this->config;
    }

    public function transport(): Transport
    {
        return $this->transport;
    }

    /** Sales invoices (scopes: invoices:read, invoices:write, ksef:send). */
    public function invoices(): Invoices
    {
        return $this->invoices ??= new Invoices($this->transport);
    }

    /** Sales orders and pay-then-invoice flow (scopes: orders:read, orders:write). */
    public function orders(): Orders
    {
        return $this->orders ??= new Orders($this->transport);
    }

    /** Contractors (scopes: contractors:read, contractors:write). */
    public function contractors(): Contractors
    {
        return $this->contractors ??= new Contractors($this->transport);
    }

    /** Product catalog (scopes: products:read, products:write). */
    public function products(): Products
    {
        return $this->products ??= new Products($this->transport);
    }

    /** Price groups (scope: products:read). */
    public function priceGroups(): PriceGroups
    {
        return $this->priceGroups ??= new PriceGroups($this->transport);
    }

    /** Incoming (cost) invoices - the KSeF inbox (scopes: incoming:read, incoming:write, incoming:vote). */
    public function incomingInvoices(): IncomingInvoices
    {
        return $this->incomingInvoices ??= new IncomingInvoices($this->transport);
    }

    /** Warehouse: stock levels, movements, booking (scopes: warehouse:read, warehouse:write). */
    public function warehouse(): Warehouse
    {
        return $this->warehouse ??= new Warehouse($this->transport);
    }

    /** Reports (scope: invoices:read; requires a plan with the warehouse module). */
    public function reports(): Reports
    {
        return $this->reports ??= new Reports($this->transport);
    }

    /** Invoice numbering series (no scope required). */
    public function invoiceSeries(): InvoiceSeries
    {
        return $this->invoiceSeries ??= new InvoiceSeries($this->transport);
    }

    /** Team bank accounts (no scope required). */
    public function bankAccounts(): BankAccounts
    {
        return $this->bankAccounts ??= new BankAccounts($this->transport);
    }

    /** NBP exchange rates (no scope required). */
    public function exchangeRates(): ExchangeRates
    {
        return $this->exchangeRates ??= new ExchangeRates($this->transport);
    }
}
