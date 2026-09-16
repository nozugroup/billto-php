<?php

declare(strict_types=1);

namespace BillTo\Http;

use BillTo\Config;
use BillTo\Exceptions\ApiException;
use BillTo\Exceptions\ErrorMapper;
use BillTo\Exceptions\RateLimitException;
use BillTo\Exceptions\TransportException;
use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * HTTP layer: builds PSR-7 requests, adds authorization and Idempotency-Key,
 * retries transient failures and maps 4xx/5xx responses to exceptions.
 */
final class Transport
{
    private readonly ClientInterface $http;

    private readonly RequestFactoryInterface $requestFactory;

    private readonly StreamFactoryInterface $streamFactory;

    /** @var callable(float): void */
    private $sleeper;

    /** @var callable(): string */
    private $keyGenerator;

    public function __construct(
        private readonly Config $config,
        ?ClientInterface $http = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
    ) {
        $this->http = $http ?? Psr18ClientDiscovery::find();
        $this->requestFactory = $requestFactory ?? Psr17FactoryDiscovery::findRequestFactory();
        $this->streamFactory = $streamFactory ?? Psr17FactoryDiscovery::findStreamFactory();
        $this->sleeper = static function (float $seconds): void {
            usleep((int) round($seconds * 1_000_000));
        };
        $this->keyGenerator = static fn (): string => IdempotencyKey::generate();
    }

    /** Replace the sleep function (tests). */
    public function withSleeper(callable $sleeper): self
    {
        $clone = clone $this;
        $clone->sleeper = $sleeper;

        return $clone;
    }

    /** Replace the Idempotency-Key generator (tests / custom key format). */
    public function withKeyGenerator(callable $generator): self
    {
        $clone = clone $this;
        $clone->keyGenerator = $generator;

        return $clone;
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public function get(string $path, array $query = []): ApiResponse
    {
        return $this->request('GET', $path, $query);
    }

    /**
     * @param  array<string, mixed>|null  $body
     */
    public function post(string $path, ?array $body = null, ?string $idempotencyKey = null): ApiResponse
    {
        return $this->request('POST', $path, [], $body ?? [], $idempotencyKey);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    public function put(string $path, array $body, ?string $idempotencyKey = null): ApiResponse
    {
        return $this->request('PUT', $path, [], $body, $idempotencyKey);
    }

    public function delete(string $path): ApiResponse
    {
        return $this->request('DELETE', $path);
    }

    /**
     * Download a binary file (PDF/XML). Accept is not forced to JSON; errors are still mapped to exceptions.
     *
     * @param  array<string, mixed>  $query
     */
    public function download(string $path, array $query = [], string $fallbackFilename = 'download.bin'): BinaryFile
    {
        $response = $this->request('GET', $path, $query, null, null, '*/*');

        return BinaryFile::fromResponse($response, $fallbackFilename);
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>|null  $body
     *
     * @throws ApiException
     * @throws TransportException
     */
    public function request(
        string $method,
        string $path,
        array $query = [],
        ?array $body = null,
        ?string $idempotencyKey = null,
        string $accept = 'application/json',
    ): ApiResponse {
        $isMutation = in_array($method, ['POST', 'PUT', 'PATCH'], true);

        if ($isMutation && $idempotencyKey === null && $this->config->autoIdempotency) {
            $idempotencyKey = ($this->keyGenerator)();
        }

        $request = $this->buildRequest($method, $path, $query, $body, $idempotencyKey, $accept);

        // A mutation without an idempotency key is not safely repeatable - never retry it.
        $retryable = ! $isMutation || $idempotencyKey !== null;
        $attempt = 0;

        while (true) {
            try {
                $psrResponse = $this->http->sendRequest($request);
            } catch (ClientExceptionInterface $e) {
                if ($retryable && $attempt < $this->config->maxRetries) {
                    ($this->sleeper)($this->backoff($attempt));
                    $attempt++;

                    continue;
                }

                throw new TransportException('Could not reach the BillTo API: '.$e->getMessage(), $e);
            }

            $response = self::toApiResponse($psrResponse);

            if ($response->status < 400) {
                return $response;
            }

            $exception = ErrorMapper::map($response);

            if ($retryable && $attempt < $this->config->maxRetries && $this->shouldRetry($exception)) {
                ($this->sleeper)($this->retryDelay($exception, $attempt));
                $attempt++;

                continue;
            }

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  array<string, mixed>|null  $body
     */
    private function buildRequest(string $method, string $path, array $query, ?array $body, ?string $idempotencyKey, string $accept): RequestInterface
    {
        $uri = $this->config->normalizedBaseUrl().'/'.ltrim($path, '/');
        $query = array_filter($query, static fn ($value) => $value !== null);

        if ($query !== []) {
            $uri .= (str_contains($uri, '?') ? '&' : '?').http_build_query(self::normalizeQuery($query), '', '&', PHP_QUERY_RFC3986);
        }

        $request = $this->requestFactory->createRequest($method, $uri)
            ->withHeader('Authorization', 'Bearer '.$this->config->token)
            ->withHeader('Accept', $accept)
            ->withHeader('User-Agent', $this->config->userAgent);

        if ($idempotencyKey !== null) {
            $request = $request->withHeader('Idempotency-Key', $idempotencyKey);
        }

        if ($body !== null) {
            $json = json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $request = $request
                ->withHeader('Content-Type', 'application/json')
                ->withBody($this->streamFactory->createStream($json));
        }

        return $request;
    }

    /**
     * Normalise query values: booleans as 1/0 (http_build_query would turn `false` into an
     * empty string), backed enums by value, dates as ISO 8601.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    private static function normalizeQuery(array $query): array
    {
        foreach ($query as $key => $value) {
            if (is_bool($value)) {
                $query[$key] = $value ? '1' : '0';
            } elseif ($value instanceof \BackedEnum) {
                $query[$key] = $value->value;
            } elseif ($value instanceof \DateTimeInterface) {
                $query[$key] = $value->format(DATE_ATOM);
            }
        }

        return $query;
    }

    private function shouldRetry(ApiException $exception): bool
    {
        if ($exception instanceof RateLimitException) {
            // A 429 without Retry-After is an exhausted plan quota (monthly), not throttling - retrying is pointless.
            return $exception->retryAfter !== null;
        }

        return in_array($exception->status, [502, 503, 504], true);
    }

    private function retryDelay(ApiException $exception, int $attempt): float
    {
        if ($exception instanceof RateLimitException && $exception->retryAfter !== null) {
            return min((float) $exception->retryAfter, $this->config->maxRetryDelay);
        }

        return $this->backoff($attempt);
    }

    private function backoff(int $attempt): float
    {
        $delay = $this->config->retryBaseDelay * (2 ** $attempt);
        $jitter = $delay * (random_int(0, 250) / 1000);

        return min($delay + $jitter, $this->config->maxRetryDelay);
    }

    private static function toApiResponse(ResponseInterface $response): ApiResponse
    {
        $headers = [];

        foreach ($response->getHeaders() as $name => $values) {
            $headers[strtolower($name)] = implode(', ', $values);
        }

        return new ApiResponse($response->getStatusCode(), (string) $response->getBody(), $headers);
    }
}
