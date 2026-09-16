<?php

declare(strict_types=1);

namespace BillTo\Http;

use BillTo\Exceptions\UnexpectedResponseException;

/**
 * Decoded API response. Rarely used directly - resources return entities and pages.
 */
final class ApiResponse
{
    /** @var array<string, mixed>|null */
    private ?array $decoded = null;

    private bool $decodeAttempted = false;

    /**
     * @param  array<string, string>  $headers  Header names in lowercase.
     */
    public function __construct(
        public readonly int $status,
        public readonly string $body,
        public readonly array $headers = [],
    ) {}

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function isJson(): bool
    {
        return str_contains(strtolower($this->header('content-type') ?? ''), 'json');
    }

    /**
     * Decoded JSON body, or null when the body is not a JSON object.
     *
     * @return array<string, mixed>|null
     */
    public function json(): ?array
    {
        if ($this->decodeAttempted) {
            return $this->decoded;
        }

        $this->decodeAttempted = true;

        if (trim($this->body) === '') {
            return $this->decoded = null;
        }

        try {
            $decoded = json_decode($this->body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $this->decoded = null;
        }

        return $this->decoded = is_array($decoded) ? $decoded : null;
    }

    /**
     * Contents of the `data` key - every resource response in the BillTo API is wrapped in `data`.
     *
     * @return array<string, mixed>|list<mixed>
     */
    public function data(): array
    {
        $json = $this->json();

        if ($json === null || ! array_key_exists('data', $json) || ! is_array($json['data'])) {
            throw new UnexpectedResponseException('API response does not contain a "data" key.', $this);
        }

        return $json['data'];
    }

    /**
     * `data` as a key => value map (a single resource).
     *
     * @return array<string, mixed>
     */
    public function dataObject(): array
    {
        $data = $this->data();

        if (array_is_list($data) && $data !== []) {
            throw new UnexpectedResponseException('Expected a single resource in "data", got a list.', $this);
        }

        /** @var array<string, mixed> $data */
        return $data;
    }

    /**
     * `data` as a list of items.
     *
     * @return list<array<string, mixed>>
     */
    public function dataList(): array
    {
        $data = $this->data();

        if (! array_is_list($data)) {
            throw new UnexpectedResponseException('Expected a list in "data", got a single resource.', $this);
        }

        /** @var list<array<string, mixed>> $data */
        return $data;
    }

    /** @return array<string, mixed> */
    public function meta(): array
    {
        $meta = $this->json()['meta'] ?? [];

        return is_array($meta) ? $meta : [];
    }

    public function message(): ?string
    {
        $message = $this->json()['message'] ?? null;

        return is_string($message) ? $message : null;
    }
}
