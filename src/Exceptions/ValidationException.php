<?php

declare(strict_types=1);

namespace BillTo\Exceptions;

use BillTo\Http\ApiResponse;

/**
 * 422 - input rejected by validation or by a business rule
 * (draft cannot be paid, no series selected, incomplete incoming invoice, ...).
 */
final class ValidationException extends ApiException
{
    /** @var array<string, list<string>> */
    public readonly array $errors;

    public function __construct(string $message, int $status, ApiResponse $response)
    {
        parent::__construct($message, $status, $response);

        $this->errors = self::normalizeErrors($response->json()['errors'] ?? []);
    }

    /** Whether a given field has an error (e.g. `items.0.vat_type`). */
    public function has(string $field): bool
    {
        return isset($this->errors[$field]);
    }

    /** First message for a field, or null. */
    public function first(string $field): ?string
    {
        return $this->errors[$field][0] ?? null;
    }

    /**
     * All messages flattened into a single list.
     *
     * @return list<string>
     */
    public function messages(): array
    {
        return array_merge([], ...array_values($this->errors));
    }

    /**
     * @return array<string, list<string>>
     */
    private static function normalizeErrors(mixed $errors): array
    {
        if (! is_array($errors)) {
            return [];
        }

        $normalized = [];

        foreach ($errors as $field => $messages) {
            $list = is_array($messages) ? array_values($messages) : [$messages];
            $normalized[(string) $field] = array_values(array_map(
                static fn ($m) => is_scalar($m) ? (string) $m : (json_encode($m, JSON_UNESCAPED_UNICODE) ?: ''),
                $list,
            ));
        }

        return $normalized;
    }
}
