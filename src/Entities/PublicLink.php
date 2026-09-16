<?php

declare(strict_types=1);

namespace BillTo\Entities;

/**
 * Public invoice page (transfer details, QR code, PDF download).
 *
 * @property-read string $public_url
 * @property-read string|null $first_viewed_at
 */
final class PublicLink extends Entity {}
