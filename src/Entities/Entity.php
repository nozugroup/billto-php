<?php

declare(strict_types=1);

namespace BillTo\Entities;

/**
 * Base entity: a thin layer over the array returned by the API.
 *
 * Fields are reachable as properties (`$invoice->invoice_number`), via array access
 * (`$invoice['totals']['gross']`) and via dot paths (`$invoice->get('totals.gross')`).
 * Subclasses document their fields with @property-read annotations for IDE completion.
 *
 * @implements \ArrayAccess<string, mixed>
 */
abstract class Entity implements \ArrayAccess, \JsonSerializable
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(protected readonly array $attributes) {}

    /** Value at a dot path, e.g. `totals.gross` or `items.0.name`. */
    public function get(string $path, mixed $default = null): mixed
    {
        $value = $this->attributes;

        foreach (explode('.', $path) as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    public function has(string $path): bool
    {
        return $this->get($path, $this) !== $this;
    }

    /** Date/time field as DateTimeImmutable (null when empty or unparsable). */
    public function date(string $path): ?\DateTimeImmutable
    {
        $value = $this->get($path);

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }

    /** Money field as float (the API returns amounts as strings with two decimals). */
    public function amount(string $path): ?float
    {
        $value = $this->get($path);

        return is_numeric($value) ? (float) $value : null;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->attributes;
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->attributes;
    }

    public function __get(string $name): mixed
    {
        return $this->attributes[$name] ?? null;
    }

    public function __isset(string $name): bool
    {
        return isset($this->attributes[$name]);
    }

    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists($offset, $this->attributes);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->attributes[$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new \LogicException(static::class.' is immutable.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new \LogicException(static::class.' is immutable.');
    }

    /**
     * @template T of Entity
     *
     * @param  class-string<T>  $class
     * @return list<T>
     */
    protected function many(string $path, string $class): array
    {
        $rows = $this->get($path);

        if (! is_array($rows)) {
            return [];
        }

        return array_values(array_map(static fn (array $row) => new $class($row), array_filter($rows, 'is_array')));
    }

    /**
     * @template T of Entity
     *
     * @param  class-string<T>  $class
     * @return T|null
     */
    protected function one(string $path, string $class): ?Entity
    {
        $row = $this->get($path);

        return is_array($row) ? new $class($row) : null;
    }
}
