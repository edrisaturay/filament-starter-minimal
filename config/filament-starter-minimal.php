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
    | Shipped UserResource (with stechstudio/filament-impersonate wired in).
    | Set 'enabled' to false (or STARTER_MINIMAL_USER_RESOURCE=false) when the
    | consuming app already registers its own UserResource on managed panels,
    | to avoid two resources fighting over /users.
    |
    | 'model' = null resolves from config('auth.providers.users.model').
    |--------------------------------------------------------------------------
    */
    'users' => [
        'enabled' => (bool) env('STARTER_MINIMAL_USER_RESOURCE', true),
        'model' => env('STARTER_MINIMAL_USER_MODEL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Panels that use the minimal plugin registry and DB sync
    |--------------------------------------------------------------------------
    */
    'managed_panels' => [
        'admin',
        'knowledge-base',
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
            'filament-knowledge-base-companion' => [
                'enabled' => false,
                'options' => [
                    'knowledge_base_panel_id' => 'knowledge-base',
                ],
            ],
        ],
        'knowledge-base' => [
            'filament-knowledge-base' => [
                'enabled' => true,
                'options' => [
                    'knowledge_base_panel_id' => 'knowledge-base',
                ],
            ],
        ],
    ],

];
