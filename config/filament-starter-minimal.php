<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Safe mode (config or cache-driven via the minimal-starter:safe-mode
    | command). When active, plugins flagged dangerousToDisable in the registry
    | are forced enabled in resolved state regardless of DB overrides.
    |--------------------------------------------------------------------------
    */
    'safe_mode' => (bool) env('STARTER_MINIMAL_SAFE_MODE', false),

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
