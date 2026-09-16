<?php

declare(strict_types=1);

use BillTo\BillTo;
use BillTo\Config;
use BillTo\Http\IdempotencyKey;
use BillTo\Tests\Support\FakeHttpClient;

it('rejects an empty token and an invalid base url', function () {
    expect(fn () => new Config(''))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new Config('t', 'not-a-url'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new Config('t', Config::DEFAULT_BASE_URL, -1))->toThrow(InvalidArgumentException::class);
});

it('is immutable via with* methods', function () {
    $config = new Config('t');
    $sandbox = $config->withBaseUrl(Config::SANDBOX_BASE_URL)->withMaxRetries(5)->withAutoIdempotency(false)->withUserAgent('shop/1.0');

    expect($config->baseUrl)->toBe(Config::DEFAULT_BASE_URL)
        ->and($config->maxRetries)->toBe(2)
        ->and($sandbox->baseUrl)->toBe(Config::SANDBOX_BASE_URL)
        ->and($sandbox->maxRetries)->toBe(5)
        ->and($sandbox->autoIdempotency)->toBeFalse()
        ->and($sandbox->userAgent)->toBe('shop/1.0')
        ->and((new Config('t', 'https://x.test/api/v1/'))->normalizedBaseUrl())->toBe('https://x.test/api/v1');
});

it('builds a sandbox client', function () {
    $billto = BillTo::sandbox('t', new FakeHttpClient);

    expect($billto->config()->baseUrl)->toBe(Config::SANDBOX_BASE_URL);
});

it('generates RFC 4122 v4 keys and deterministic operation keys', function () {
    $key = IdempotencyKey::generate();

    expect($key)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/')
        ->and(IdempotencyKey::generate())->not->toBe($key)
        ->and(IdempotencyKey::fromOperation('webhook-1'))->toBe(IdempotencyKey::fromOperation('webhook-1'))
        ->and(IdempotencyKey::fromOperation('webhook-1'))->not->toBe(IdempotencyKey::fromOperation('webhook-2'))
        ->and(strlen(IdempotencyKey::fromOperation('x')))->toBe(64);
});

// ── API identification header ────────────────────────────────────────────────

it('sends a compliant User-Agent out of the box', function () {
    // The header carries product, version and a contact segment.
    expect((new Config('t'))->userAgent)
        ->toStartWith('billto-php/'.Config::VERSION.' (+https://github.com/nozugroup/billto-php')
        ->toContain('compat='.Config::API_COMPAT);
});

it('lets an integration identify itself and keeps the SDK token', function () {
    $config = (new Config('t'))->identify('MyERP', '2.1.0', 'https://myerp.example/contact');

    expect($config->userAgent)
        ->toBe('MyERP/2.1.0 (+https://myerp.example/contact; compat='.Config::API_COMPAT.') billto-php/'.Config::VERSION);
});

it('allows an integration to declare its own compat, or none', function () {
    expect((new Config('t'))->identify('MyERP', '2.1.0', 'dev@myerp.example', '2026-01-01')->userAgent)
        ->toContain('compat=2026-01-01')
        ->and((new Config('t'))->identify('MyERP', '2.1.0', 'dev@myerp.example', null)->userAgent)
        ->toBe('MyERP/2.1.0 (+dev@myerp.example) billto-php/'.Config::VERSION);
});

it('strips characters that would break the header structure', function () {
    // An unescaped ")" would close the metadata group early, so the separators are stripped.
    $header = (new Config('t'))->identify('My)ERP', '2.1 (beta)', 'https://myerp.example/a)b')->userAgent;

    expect(substr_count($header, '('))->toBe(1)
        ->and(substr_count($header, ')'))->toBe(1)
        ->and($header)->toStartWith('MyERP/2.1beta (+https://myerp.example/ab;');
});

it('refuses an identification without a contact', function () {
    expect(fn () => (new Config('t'))->identify('MyERP', '2.1.0', ''))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => (new Config('t'))->identify('', '2.1.0', 'dev@myerp.example'))
        ->toThrow(InvalidArgumentException::class);
});

it('keeps identification immutable', function () {
    $base = new Config('t');
    $identified = $base->identify('MyERP', '2.1.0', 'dev@myerp.example');

    expect($base->userAgent)->not->toBe($identified->userAgent)
        ->and($identified->token)->toBe($base->token)
        ->and($identified->baseUrl)->toBe($base->baseUrl);
});
