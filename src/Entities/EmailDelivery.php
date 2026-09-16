<?php

declare(strict_types=1);

namespace BillTo\Entities;

/**
 * Result of e-mailing an invoice.
 *
 * @property-read string $sent_to
 * @property-read string $sent_at
 * @property-read string $public_url
 */
final class EmailDelivery extends Entity {}
