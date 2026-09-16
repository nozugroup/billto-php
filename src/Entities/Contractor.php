<?php

declare(strict_types=1);

namespace BillTo\Entities;

/**
 * Contractor.
 *
 * @property-read string $id
 * @property-read string $type own|external
 * @property-read string $name
 * @property-read string|null $email
 * @property-read string|null $phone
 * @property-read string|null $tax_registration_type local|eu|noneu|none
 * @property-read string|null $tax_number
 * @property-read string $tax_country_code
 * @property-read string|null $client_number
 * @property-read string|null $price_group_id
 * @property-read list<array{id: string, type: string, country_code: string, postal_code: string|null, locality: string|null, street: string|null, building_number: string|null, unit_number: string|null}> $addresses
 * @property-read array<string, mixed>|null $payment_score Only present with `include=score`.
 * @property-read string $created_at
 * @property-read string $updated_at
 */
final class Contractor extends Entity
{
    /** @return array<string, mixed>|null */
    public function registeredAddress(): ?array
    {
        return $this->addressOfType('registered');
    }

    /** @return array<string, mixed>|null */
    public function correspondenceAddress(): ?array
    {
        return $this->addressOfType('correspondence');
    }

    /** @return array<string, mixed>|null */
    private function addressOfType(string $type): ?array
    {
        foreach ((array) $this->addresses as $address) {
            if (is_array($address) && ($address['type'] ?? null) === $type) {
                return $address;
            }
        }

        return null;
    }
}
