<?php
return [
    /*
    |--------------------------------------------------------------------------
    | Default Courier
    |--------------------------------------------------------------------------
    |
    | This is the default courier that will be used when no courier is specified.
    |
    */
    'default' => env('DEFAULT_COURIER', 'aramex'),

    /*
    |--------------------------------------------------------------------------
    | Courier Configurations
    |--------------------------------------------------------------------------
    |
    | Configuration for each courier integration.
    |
    */

    'aramex' => [
        'enabled' => env('ARAMEX_ENABLED', false),
        'base_url' => env('ARAMEX_API_URL', 'https://ws.aramex.net'),
        
        // ClientInfo Credentials
        'username' => env('ARAMEX_USERNAME'),
        'password' => env('ARAMEX_PASSWORD'),
        'account_number' => env('ARAMEX_ACCOUNT_NUMBER'),
        'account_pin' => env('ARAMEX_ACCOUNT_PIN'),
        'account_entity' => env('ARAMEX_ACCOUNT_ENTITY'),
        'account_country_code' => env('ARAMEX_ACCOUNT_COUNTRY_CODE', 'SA'),

        'rate_limit' => [
            'requests' => 60,
            'per_minutes' => 1,
        ],
    ],


    /*
    |--------------------------------------------------------------------------
    | HTTP Client Configuration
    |--------------------------------------------------------------------------
    */
    'http' => [
        'timeout' => 30, // seconds
        'retry' => [
            'times' => 3,
            'sleep' => 1000, // milliseconds
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Circuit Breaker Configuration
    |--------------------------------------------------------------------------
    */
    'circuit_breaker' => [
        'failure_threshold' => 5,
        'timeout_seconds' => 60,
        'success_threshold' => 2,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'tracking_ttl' => [
            'active' => 300,    // 5 minutes for active shipments
            'terminal' => 3600, // 1 hour for delivered/cancelled
        ],
        'label_ttl' => 3600, // 1 hour
    ],
];
