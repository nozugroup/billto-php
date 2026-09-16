<?php

declare(strict_types=1);

namespace BillTo\Tests\Support;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * In-memory PSR-18 client: queue responses (or exceptions), inspect sent requests.
 */
final class FakeHttpClient implements ClientInterface
{
    /** @var list<ResponseInterface|\Throwable> */
    private array $queue = [];

    /** @var list<RequestInterface> */
    public array $requests = [];

    /** @var list<float> */
    public array $sleeps = [];

    public function queue(ResponseInterface|\Throwable ...$responses): self
    {
        foreach ($responses as $response) {
            $this->queue[] = $response;
        }

        return $this;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;

        if ($this->queue === []) {
            throw new \LogicException('FakeHttpClient: no queued response for '.$request->getMethod().' '.$request->getUri());
        }

        $next = array_shift($this->queue);

        if ($next instanceof \Throwable) {
            throw $next;
        }

        return $next;
    }

    public function lastRequest(): RequestInterface
    {
        $last = end($this->requests);

        if ($last === false) {
            throw new \LogicException('FakeHttpClient: no request was sent.');
        }

        return $last;
    }

    /** @return array<string, mixed> */
    public function lastJsonBody(): array
    {
        $decoded = json_decode((string) $this->lastRequest()->getBody(), true, 512, JSON_THROW_ON_ERROR);

        return is_array($decoded) ? $decoded : [];
    }
}

/** Network-level failure implementing the PSR-18 marker interface. */
final class NetworkFailure extends \RuntimeException implements ClientExceptionInterface {}
