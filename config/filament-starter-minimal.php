<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Safe mode (config or env STARTER_MINIMAL_SAFE_MODE, or cache via artisan)
    |--------------------------------------------------------------------------
    */
    'safe_mode' => (bool) env('STARTER_MINIMAL_SAFE_MODE', false),

    'tenancy' => [
        'enabled' => false,
    ],

    'superadmin' => [
        'role' => 'super_admin',
    ],

    /*
    |--------------------------------------------------------------------------
    | Panels that use the minimal plugin registry and DB sync
    |--------------------------------------------------------------------------
    */
    'managed_panels' => [
        'admin',
    ],

    /*
    |--------------------------------------------------------------------------
    | Per-panel plugin defaults (merged with DB overrides)
    |--------------------------------------------------------------------------
    */
    'plugin_defaults' => [
        'admin' => [
            'filament-shield' => [
                'enabled' => true,
                'options' => [],
            ],
        ],
    ],

];
