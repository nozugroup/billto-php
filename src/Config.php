<?php

declare(strict_types=1);

namespace BillTo;

/**
 * Client configuration. Immutable - every `with*()` call returns a new instance.
 */
final class Config
{
    public const DEFAULT_BASE_URL = 'https://billto.pl/api/v1';

    public const SANDBOX_BASE_URL = 'https://sandbox.billto.pl/api/v1';

    public const VERSION = '1.0.0';

    /**
     * API version this SDK release was tested against, sent as the `compat` segment of the
     * User-Agent header. Informational only: BillTo uses it to target notifications about
     * upcoming changes, never to select API behaviour.
     */
    public const API_COMPAT = '2026-09-15';

    /**
     * Identification of the SDK itself, used when the application does not provide its own.
     *
     * Names the library, not the calling application. Software distributed to other BillTo
     * accounts sets its own identity with {@see identify()}.
     */
    public const DEFAULT_USER_AGENT = 'billto-php/'.self::VERSION
        .' (+https://github.com/nozugroup/billto-php; compat='.self::API_COMPAT.')';

    /**
     * @param  int  $maxRetries  Maximum number of retries for 429 (with Retry-After), 502/503/504 and network errors.
     * @param  bool  $autoIdempotency  Attach a random Idempotency-Key to every POST/PUT that has no explicit key.
     * @param  float  $retryBaseDelay  Base retry delay in seconds (grows exponentially) when no Retry-After is given.
     * @param  float  $maxRetryDelay  Upper bound for a single retry delay in seconds.
     */
    public function __construct(
        public readonly string $token,
        public readonly string $baseUrl = self::DEFAULT_BASE_URL,
        public readonly int $maxRetries = 2,
        public readonly bool $autoIdempotency = true,
        public readonly float $retryBaseDelay = 0.5,
        public readonly float $maxRetryDelay = 10.0,
        public readonly string $userAgent = self::DEFAULT_USER_AGENT,
    ) {
        if (trim($token) === '') {
            throw new \InvalidArgumentException('BillTo API token must not be empty.');
        }

        if (! filter_var($baseUrl, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException("Invalid API base URL: {$baseUrl}");
        }

        if ($maxRetries < 0) {
            throw new \InvalidArgumentException('maxRetries must not be negative.');
        }
    }

    public function withBaseUrl(string $baseUrl): self
    {
        return new self($this->token, $baseUrl, $this->maxRetries, $this->autoIdempotency, $this->retryBaseDelay, $this->maxRetryDelay, $this->userAgent);
    }

    public function withMaxRetries(int $maxRetries): self
    {
        return new self($this->token, $this->baseUrl, $maxRetries, $this->autoIdempotency, $this->retryBaseDelay, $this->maxRetryDelay, $this->userAgent);
    }

    public function withAutoIdempotency(bool $enabled): self
    {
        return new self($this->token, $this->baseUrl, $this->maxRetries, $enabled, $this->retryBaseDelay, $this->maxRetryDelay, $this->userAgent);
    }

    public function withUserAgent(string $userAgent): self
    {
        return new self($this->token, $this->baseUrl, $this->maxRetries, $this->autoIdempotency, $this->retryBaseDelay, $this->maxRetryDelay, $userAgent);
    }

    /**
     * Sets the calling application as the identity in the `User-Agent` header.
     *
     * Required for software distributed to other BillTo accounts - a plugin, a module, a hosted
     * service. The SDK token is appended after the application token.
     *
     * ```php
     * $config = (new Config($token))->identify('MyERP', '2.1.0', 'https://myerp.example/contact');
     * // MyERP/2.1.0 (+https://myerp.example/contact; compat=2026-09-15) billto-php/0.1.0
     * ```
     *
     * @param  string  $product  Stable product name, constant across releases.
     * @param  string  $version  Version of this deployment.
     * @param  string  $contact  Working e-mail address, or a page with a contact channel.
     * @param  string|null  $compat  API version your release was tested against.
     */
    public function identify(string $product, string $version, string $contact, ?string $compat = self::API_COMPAT): self
    {
        foreach (['product' => $product, 'version' => $version, 'contact' => $contact] as $name => $value) {
            if (trim($value) === '') {
                throw new \InvalidArgumentException("User-Agent {$name} must not be empty.");
            }
        }

        // Characters that break the header structure are removed rather than escaped; ")" in
        // particular would close the metadata group early.
        $clean = static fn (string $value, int $max): string => substr(
            (string) preg_replace('/[^A-Za-z0-9._\-\/:@+?=&%]/', '', $value), 0, $max
        );

        $header = $clean($product, 60).'/'.$clean($version, 40).' (+'.$clean($contact, 180);

        if ($compat !== null && trim($compat) !== '') {
            $header .= '; compat='.$clean($compat, 40);
        }

        return $this->withUserAgent($header.') billto-php/'.self::VERSION);
    }

    /** Base URL without a trailing slash. */
    public function normalizedBaseUrl(): string
    {
        return rtrim($this->baseUrl, '/');
    }
}
