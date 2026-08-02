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

    /*
    |--------------------------------------------------------------------------
    | Auto-register the panel plugin
    |--------------------------------------------------------------------------
    | When true (default), FilamentStarterMinimalPlugin is automatically added
    | to every panel listed in `managed_panels` — consumers do not need to
    | include it manually in their PanelProvider's ->plugins([...]) array.
    | Set to false to opt out and register the plugin manually.
    |--------------------------------------------------------------------------
    */
    'auto_register_panel_plugin' => (bool) env('STARTER_MINIMAL_AUTO_REGISTER', true),

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
    | Spatie Media Library ↔ Slimani Media Manager bridge
    |--------------------------------------------------------------------------
    | When a Spatie Media row is created on a configured model, mirror it as
    | a Slimani Media Manager File so the file shows up in the Media Manager
    | UI organised by folders. Folder hierarchy is:
    |
    |   /{parent_property}/{collection_name}/file.ext
    |
    | e.g. a SpatieMediaLibraryFileUpload::make('passport') on a User whose
    | configured folder_property is 'name' lands in /alice/passport/...
    |
    | Trade-off: the bridge COPIES the physical file into the Slimani File's
    | own Spatie media collection. This means 2x storage per uploaded file
    | (original on User, copy on File). It is the safe choice because moving
    | the media would break $user->getMedia('passport') and consequently any
    | Filament form that uses SpatieMediaLibraryFileUpload.
    |
    | Bridging is opt-in per model. List a model in 'models' to enable it.
    |--------------------------------------------------------------------------
    */
    'media_manager_bridge' => [
        'enabled' => (bool) env('STARTER_MINIMAL_MEDIA_BRIDGE', true),

        // Per-model config. Key = FQCN, value = ['folder_property' => 'attribute'].
        // Models NOT listed here are NOT bridged.
        'models' => [
            // 'App\Models\User' => ['folder_property' => 'name'],
        ],

        // Tried in order when 'folder_property' is unset for a listed model.
        'default_folder_properties' => ['name', 'title', 'slug', 'username'],

        // Used when none of the above resolve to a non-empty value.
        'fallback_folder' => 'Uploads',
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
