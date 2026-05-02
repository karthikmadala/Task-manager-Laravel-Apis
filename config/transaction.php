<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Gas Configuration
    |--------------------------------------------------------------------------
    */
    'gas' => [
        'oracle_url' => env('GAS_PRICE_ORACLE_URL'),
        'default_limit' => env('DEFAULT_GAS_LIMIT', 21000),
        'max_limit' => env('MAX_GAS_LIMIT', 1000000),
        'safety_margin' => 1.2, // 20% buffer
        'cache_ttl' => 30, // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring Configuration
    |--------------------------------------------------------------------------
    */
    'monitoring' => [
        'check_interval_minutes' => env('TRANSACTION_CHECK_INTERVAL_MINUTES', 2),
        'confirmation_threshold' => env('CONFIRMATION_THRESHOLD', 12),
        'max_retry_attempts' => env('MAX_RETRY_ATTEMPTS', 3),
        'retry_delay_seconds' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Broadcast Configuration
    |--------------------------------------------------------------------------
    */
    'broadcast' => [
        'timeout_seconds' => 30,
        'max_attempts' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration
    |--------------------------------------------------------------------------
    */
    'webhooks' => [
        'alchemy_secret' => env('ALCHEMY_WEBHOOK_SECRET'),
        'etherscan_secret' => env('ETHERSCAN_WEBHOOK_SECRET'),
    ],

];