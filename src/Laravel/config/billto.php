<?php

declare(strict_types=1);

return [
    /*
     * Team API token generated in BillTo: Settings -> API tokens.
     */
    'token' => env('BILLTO_TOKEN'),

    /*
     * API base URL. Use https://sandbox.billto.pl/api/v1 for the test environment.
     */
    'base_url' => env('BILLTO_BASE_URL', 'https://billto.pl/api/v1'),

    /*
     * Retries for 429 (with Retry-After), 502/503/504 and network errors.
     */
    'max_retries' => (int) env('BILLTO_MAX_RETRIES', 2),

    /*
     * Attach a random Idempotency-Key to every POST/PUT without an explicit key.
     */
    'auto_idempotency' => (bool) env('BILLTO_AUTO_IDEMPOTENCY', true),
];
