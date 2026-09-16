<?php

declare(strict_types=1);

namespace BillTo\Entities;

/**
 * Team bank account.
 *
 * @property-read string $id
 * @property-read string|null $owner_name
 * @property-read string $iban
 * @property-read string $formatted_iban
 * @property-read string|null $bank_name
 * @property-read string|null $bic
 * @property-read string $currency
 * @property-read string|null $label
 * @property-read bool $is_default
 * @property-read bool $active
 */
final class BankAccount extends Entity {}
