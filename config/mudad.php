<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Mudad WPS API
    |--------------------------------------------------------------------------
    |
    | Mudad (مدد) is the Saudi platform for Wage Protection System (WPS)
    | payroll submissions, operated through authorised banking channels.
    |
    | Set MUDAD_API_KEY and MUDAD_BASE_URL in your .env file.
    | Never commit real credentials to version control.
    |
    */

    'base_url' => env('MUDAD_BASE_URL', 'https://api.mudad.com.sa/v1'),

    'api_key' => env('MUDAD_API_KEY'),

    // Request timeout in seconds
    'timeout' => (int) env('MUDAD_TIMEOUT', 30),

    // Retry up to this many times on transient connection failures
    'retry_times' => (int) env('MUDAD_RETRY_TIMES', 3),

    // Base delay in milliseconds between retries (doubles each attempt)
    'retry_delay_ms' => (int) env('MUDAD_RETRY_DELAY_MS', 200),

];
