<?php

declare(strict_types=1);

namespace BillTo\Entities;

/**
 * Invoice party (seller / buyer) - a snapshot taken at issue time.
 *
 * @property-read string $role seller|buyer
 * @property-read string $name
 * @property-read string|null $tax_type local|eu|noneu|none
 * @property-read string|null $tax_number
 * @property-read string|null $tax_country
 * @property-read string|null $country_code
 * @property-read string|null $email
 * @property-read string|null $phone
 * @property-read string|null $client_number
 */
final class InvoiceParty extends Entity {}
