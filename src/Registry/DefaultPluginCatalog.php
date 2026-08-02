<?php

namespace EdrisaTuray\FilamentStarterMinimal\Registry;

use EdrisaTuray\FilamentStarterMinimal\Contracts\PluginRegistryContract;
use Filament\Panel;

/**
 * Ships the default catalog of Filament panel plugins this starter knows about.
 * Every installer is class_exists-guarded so the package can list a plugin
 * without forcing the consumer to composer-require it. Class names below are
 * the canonical ones at time of writing — if a vendor renames their plugin
 * class, override that entry via FilamentStarterMinimalPlugin::withPlugin().
 */
class DefaultPluginCatalog
{
    public static function register(PluginRegistryContract $registry): void
    {
        foreach (self::definitions() as $definition) {
            $registry->register($definition);
        }
    }

    /**
     * @return array<int, PluginDefinition>
     */
    public static function definitions(): array
    {
        return [
            new PluginDefinition(
                key: 'filament-shield',
                label: 'Filament Shield',
                installer: function (Panel $panel, array $options): Panel {
                    $class = '\\BezhanSalleh\\FilamentShield\\FilamentShieldPlugin';
                    if (class_exists($class)) {
                        $panel->plugin($class::make());
                    }

                    return $panel;
                },
                defaultEnabled: true,
                dangerousToDisable: true,
                class: 'BezhanSalleh\\FilamentShield\\FilamentShieldPlugin',
                package: 'bezhansalleh/filament-shield',
            ),

            new PluginDefinition(
                key: 'filament-panel-switch',
                label: 'Panel Switch',
                installer: function (Panel $panel, array $options): Panel {
                    $class = '\\BezhanSalleh\\PanelSwitch\\PanelSwitch';
                    if (class_exists($class)) {
                        // Panel Switch is configured globally via static configureUsing().
                        // Repeated calls overwrite — accept the last managed panel's options.
                        $class::configureUsing(function ($switch) use ($options): void {
                            if (isset($options['icons']) && method_exists($switch, 'icons')) {
                                $switch->icons($options['icons']);
                            }
                            if (isset($options['simple']) && method_exists($switch, 'simple')) {
                                $switch->simple($options['simple']);
                            }
                        });
                    }

                    return $panel;
                },
                class: 'BezhanSalleh\\PanelSwitch\\PanelSwitch',
                package: 'bezhansalleh/filament-panel-switch',
            ),

            new PluginDefinition(
                key: 'filament-exceptions',
                label: 'Filament Exceptions',
                installer: function (Panel $panel, array $options): Panel {
                    $class = '\\BezhanSalleh\\FilamentExceptions\\FilamentExceptionsPlugin';
                    if (class_exists($class)) {
                        $panel->plugin($class::make());
                    }

                    return $panel;
                },
                class: 'BezhanSalleh\\FilamentExceptions\\FilamentExceptionsPlugin',
                package: 'bezhansalleh/filament-exceptions',
            ),

            new PluginDefinition(
                key: 'filament-language-switch',
                label: 'Language Switch',
                installer: function (Panel $panel, array $options): Panel {
                    $class = '\\BezhanSalleh\\LanguageSwitch\\LanguageSwitch';
                    if (class_exists($class)) {
                        $class::configureUsing(function ($switch) use ($options): void {
                            if (isset($options['locales']) && method_exists($switch, 'locales')) {
                                $switch->locales($options['locales']);
                            }
                        });
                    }

                    return $panel;
                },
                defaultOptions: ['locales' => ['en']],
                class: 'BezhanSalleh\\LanguageSwitch\\LanguageSwitch',
                package: 'bezhansalleh/filament-language-switch',
            ),

            new PluginDefinition(
                key: 'filament-breezy',
                label: 'Breezy (profile / 2FA)',
                installer: function (Panel $panel, array $options): Panel {
                    $class = '\\Jeffgreco13\\FilamentBreezy\\BreezyCore';
                    if (! class_exists($class)) {
                        return $panel;
                    }

                    $plugin = $class::make();

                    if (($options['my_profile'] ?? true) && method_exists($plugin, 'myProfile')) {
                        $plugin->myProfile(
                            shouldRegisterUserMenu: $options['register_user_menu'] ?? true,
                            shouldRegisterNavigation: $options['register_navigation'] ?? false,
                            hasAvatars: $options['avatars'] ?? false,
                        );
                    }

                    if (($options['two_factor'] ?? true) && method_exists($plugin, 'enableTwoFactorAuthentication')) {
                        $plugin->enableTwoFactorAuthentication(
                            force: $options['force_two_factor'] ?? false,
                        );
                    }

                    if (($options['sanctum_tokens'] ?? false) && method_exists($plugin, 'enableSanctumTokens')) {
                        $plugin->enableSanctumTokens();
                    }

                    $panel->plugin($plugin);

                    return $panel;
                },
                defaultOptions: [
                    'my_profile' => true,
                    'two_factor' => true,
                    'register_user_menu' => true,
                    'register_navigation' => false,
                    'avatars' => false,
                    'force_two_factor' => false,
                    'sanctum_tokens' => false,
                ],
                class: 'Jeffgreco13\\FilamentBreezy\\BreezyCore',
                package: 'jeffgreco13/filament-breezy',
            ),

            new PluginDefinition(
                key: 'filament-auth-designer',
                label: 'Auth Designer (branded auth pages)',
                installer: function (Panel $panel, array $options): Panel {
                    $class = '\\Caresome\\FilamentAuthDesigner\\AuthDesignerPlugin';
                    if (! class_exists($class)) {
                        return $panel;
                    }

                    $plugin = $class::make();

                    $position = self::resolveMediaPosition($options['media_position'] ?? null);
                    $media = $options['media'] ?? null;
                    $mediaSize = $options['media_size'] ?? null;
                    $blur = $options['blur'] ?? null;

                    $configure = function ($config) use ($media, $position, $mediaSize, $blur) {
                        if ($media !== null && method_exists($config, 'media')) {
                            $config->media(asset($media));
                        }
                        if ($position !== null && method_exists($config, 'mediaPosition')) {
                            $config->mediaPosition($position);
                        }
                        if ($mediaSize !== null && method_exists($config, 'mediaSize')) {
                            $config->mediaSize($mediaSize);
                        }
                        if ($blur !== null && method_exists($config, 'blur')) {
                            $config->blur((int) $blur);
                        }

                        return $config;
                    };

                    if ($media !== null && method_exists($plugin, 'defaults')) {
                        $plugin->defaults($configure);
                    }

                    $pages = is_array($options['pages'] ?? null) ? $options['pages'] : ['login'];
                    foreach ($pages as $page) {
                        if (is_string($page) && method_exists($plugin, $page)) {
                            $plugin->{$page}();
                        }
                    }

                    if (($options['theme_toggle'] ?? true) && method_exists($plugin, 'themeToggle')) {
                        $plugin->themeToggle();
                    }

                    $panel->plugin($plugin);

                    return $panel;
                },
                defaultOptions: [
                    'theme_toggle' => true,
                    'pages' => ['login', 'registration', 'passwordReset', 'emailVerification'],
                    'media' => null,
                    'media_position' => 'Cover',
                    'media_size' => null,
                    'blur' => null,
                ],
                class: 'Caresome\\FilamentAuthDesigner\\AuthDesignerPlugin',
                package: 'caresome/filament-auth-designer',
            ),

            new PluginDefinition(
                key: 'filament-logger',
                label: 'Activity Logger',
                installer: function (Panel $panel, array $options): Panel {
                    // filament-logger has no Plugin class; it auto-registers via service
                    // provider. Consumers must opt the activity resource into the panel.
                    if (class_exists('\\MrAdder\\FilamentLogger\\FilamentLoggerServiceProvider')) {
                        $resource = config('filament-logger.activity_resource');
                        if (is_string($resource) && class_exists($resource)) {
                            $panel->resources([$resource]);
                        }
                    }

                    return $panel;
                },
                class: 'MrAdder\\FilamentLogger\\FilamentLoggerServiceProvider',
                package: 'mradder/filament-logger',
            ),

            new PluginDefinition(
                key: 'filament-connection-badge',
                label: 'Database Connection Badge',
                installer: function (Panel $panel, array $options): Panel {
                    // No Plugin class — auto-registers via render hooks in the service
                    // provider. Toggling at the panel level is informational only.
                    return $panel;
                },
                class: 'Rawand\\FilamentConnectionBadge\\FilamentConnectionBadgeServiceProvider',
                package: 'rawand201/filament-connection-badge',
            ),

            new PluginDefinition(
                key: 'filament-media-manager',
                label: 'Media Manager',
                installer: function (Panel $panel, array $options): Panel {
                    $class = '\\Slimani\\MediaManager\\MediaManagerPlugin';
                    if (class_exists($class)) {
                        $panel->plugin($class::make());
                    }

                    return $panel;
                },
                class: 'Slimani\\MediaManager\\MediaManagerPlugin',
                package: 'slimani/filament-media-manager',
            ),

            new PluginDefinition(
                key: 'filament-menu-builder',
                label: 'Menu Builder',
                installer: function (Panel $panel, array $options): Panel {
                    $class = '\\Datlechin\\FilamentMenuBuilder\\FilamentMenuBuilderPlugin';
                    if (class_exists($class)) {
                        $panel->plugin($class::make());
                    }

                    return $panel;
                },
                class: 'Datlechin\\FilamentMenuBuilder\\FilamentMenuBuilderPlugin',
                package: 'datlechin/filament-menu-builder',
            ),

            new PluginDefinition(
                key: 'filament-api-service',
                label: 'API Service',
                installer: function (Panel $panel, array $options): Panel {
                    $class = '\\Rupadana\\ApiService\\ApiServicePlugin';
                    if (class_exists($class)) {
                        $panel->plugin($class::make());
                    }

                    return $panel;
                },
                class: 'Rupadana\\ApiService\\ApiServicePlugin',
                package: 'rupadana/filament-api-service',
            ),

            new PluginDefinition(
                key: 'filament-resource-lock',
                label: 'Resource Lock',
                installer: function (Panel $panel, array $options): Panel {
                    $class = '\\Kenepa\\ResourceLock\\ResourceLockPlugin';
                    if (class_exists($class)) {
                        $panel->plugin($class::make());
                    }

                    return $panel;
                },
                class: 'Kenepa\\ResourceLock\\ResourceLockPlugin',
                package: 'kenepa/resource-lock',
            ),

            new PluginDefinition(
                key: 'filament-spatie-health',
                label: 'Spatie Laravel Health',
                installer: function (Panel $panel, array $options): Panel {
                    $class = '\\ShuvroRoy\\FilamentSpatieLaravelHealth\\FilamentSpatieLaravelHealthPlugin';
                    if (class_exists($class)) {
                        $panel->plugin($class::make());
                    }

                    return $panel;
                },
                class: 'ShuvroRoy\\FilamentSpatieLaravelHealth\\FilamentSpatieLaravelHealthPlugin',
                package: 'shuvroroy/filament-spatie-laravel-health',
            ),

            new PluginDefinition(
                key: 'filament-spatie-backup',
                label: 'Spatie Laravel Backup',
                installer: function (Panel $panel, array $options): Panel {
                    $class = '\\ShuvroRoy\\FilamentSpatieLaravelBackup\\FilamentSpatieLaravelBackupPlugin';
                    if (class_exists($class)) {
                        $panel->plugin($class::make());
                    }

                    return $panel;
                },
                class: 'ShuvroRoy\\FilamentSpatieLaravelBackup\\FilamentSpatieLaravelBackupPlugin',
                package: 'shuvroroy/filament-spatie-laravel-backup',
            ),

            new PluginDefinition(
                key: 'filament-global-search-modal',
                label: 'Global Search Modal',
                installer: function (Panel $panel, array $options): Panel {
                    $class = '\\CharrafiMed\\GlobalSearchModal\\GlobalSearchModalPlugin';
                    if (class_exists($class)) {
                        $panel->plugin($class::make());
                    }

                    return $panel;
                },
                class: 'CharrafiMed\\GlobalSearchModal\\GlobalSearchModalPlugin',
                package: 'charrafimed/global-search-modal',
            ),

            new PluginDefinition(
                key: 'filament-spotlight',
                label: 'Spotlight (⌘K)',
                installer: function (Panel $panel, array $options): Panel {
                    $class = '\\pxlrbt\\FilamentSpotlight\\SpotlightPlugin';
                    if (class_exists($class)) {
                        $panel->plugin($class::make());
                    }

                    return $panel;
                },
                class: 'pxlrbt\\FilamentSpotlight\\SpotlightPlugin',
                package: 'pxlrbt/filament-spotlight',
            ),

            new PluginDefinition(
                key: 'filament-knowledge-base',
                label: 'Knowledge Base (documentation panel)',
                installer: function (Panel $panel, array $options): Panel {
                    $knowledgeBasePanelId = $options['knowledge_base_panel_id'] ?? 'knowledge-base';

                    if ($panel->getId() !== $knowledgeBasePanelId) {
                        return $panel;
                    }

                    $class = '\\Guava\\FilamentKnowledgeBase\\Plugins\\KnowledgeBasePlugin';
                    if (class_exists($class)) {
                        $panel->plugin($class::make());
                    }

                    return $panel;
                },
                defaultOptions: [
                    'knowledge_base_panel_id' => 'knowledge-base',
                ],
                class: 'Guava\\FilamentKnowledgeBase\\Plugins\\KnowledgeBasePlugin',
                package: 'guava/filament-knowledge-base',
            ),

            new PluginDefinition(
                key: 'filament-knowledge-base-companion',
                label: 'Knowledge Base Companion',
                installer: function (Panel $panel, array $options): Panel {
                    $knowledgeBasePanelId = $options['knowledge_base_panel_id'] ?? 'knowledge-base';

                    if ($panel->getId() === $knowledgeBasePanelId) {
                        return $panel;
                    }

                    $class = '\\Guava\\FilamentKnowledgeBase\\Plugins\\KnowledgeBaseCompanionPlugin';
                    if (! class_exists($class)) {
                        return $panel;
                    }

                    $plugin = $class::make()->knowledgeBasePanelId($knowledgeBasePanelId);

                    if (($options['modal_previews'] ?? false) && method_exists($plugin, 'modalPreviews')) {
                        $plugin->modalPreviews();
                    }

                    if (($options['slide_over_previews'] ?? false) && method_exists($plugin, 'slideOverPreviews')) {
                        $plugin->slideOverPreviews();
                    }

                    if (($options['modal_title_breadcrumbs'] ?? false) && method_exists($plugin, 'modalTitleBreadcrumbs')) {
                        $plugin->modalTitleBreadcrumbs();
                    }

                    if (($options['open_documentation_in_new_tab'] ?? false) && method_exists($plugin, 'openDocumentationInNewTab')) {
                        $plugin->openDocumentationInNewTab();
                    }

                    $panel->plugin($plugin);

                    return $panel;
                },
                defaultOptions: [
                    'knowledge_base_panel_id' => 'knowledge-base',
                    'modal_previews' => false,
                    'slide_over_previews' => false,
                    'modal_title_breadcrumbs' => false,
                    'open_documentation_in_new_tab' => false,
                ],
                class: 'Guava\\FilamentKnowledgeBase\\Plugins\\KnowledgeBaseCompanionPlugin',
                package: 'guava/filament-knowledge-base',
            ),

            new PluginDefinition(
                key: 'filament-log-viewer',
                label: 'Log Viewer',
                installer: function (Panel $panel, array $options): Panel {
                    $class = '\\AchyutN\\FilamentLogViewer\\FilamentLogViewer';
                    if (! class_exists($class)) {
                        return $panel;
                    }

                    $plugin = $class::make();

                    if (isset($options['authorize']) && method_exists($plugin, 'authorize')) {
                        $plugin->authorize($options['authorize']);
                    }
                    if (isset($options['register_navigation']) && method_exists($plugin, 'registerNavigation')) {
                        $plugin->registerNavigation((bool) $options['register_navigation']);
                    }
                    if (isset($options['navigation_group']) && method_exists($plugin, 'navigationGroup')) {
                        $plugin->navigationGroup($options['navigation_group']);
                    }
                    if (isset($options['navigation_icon']) && method_exists($plugin, 'navigationIcon')) {
                        $plugin->navigationIcon($options['navigation_icon']);
                    }
                    if (isset($options['navigation_label']) && method_exists($plugin, 'navigationLabel')) {
                        $plugin->navigationLabel($options['navigation_label']);
                    }
                    if (isset($options['navigation_sort']) && method_exists($plugin, 'navigationSort')) {
                        $plugin->navigationSort((int) $options['navigation_sort']);
                    }
                    if (isset($options['navigation_url']) && method_exists($plugin, 'navigationUrl')) {
                        $plugin->navigationUrl($options['navigation_url']);
                    }
                    if (array_key_exists('polling_time', $options) && method_exists($plugin, 'pollingTime')) {
                        $plugin->pollingTime($options['polling_time']);
                    }

                    $panel->plugin($plugin);

                    return $panel;
                },
                defaultOptions: [
                    'authorize' => true,
                    'register_navigation' => true,
                    'navigation_group' => 'System',
                    'navigation_icon' => 'heroicon-o-document-text',
                    'navigation_label' => 'Log Viewer',
                    'navigation_sort' => 10,
                    'navigation_url' => '/logs',
                    'polling_time' => null,
                ],
                class: 'AchyutN\\FilamentLogViewer\\FilamentLogViewer',
                package: 'achyutn/filament-log-viewer',
            ),

            new PluginDefinition(
                key: 'filament-impersonate',
                label: 'Impersonate (user impersonation)',
                installer: function (Panel $panel, array $options): Panel {
                    // stechstudio/filament-impersonate exposes no Filament Plugin class.
                    // It auto-registers via its service provider and ships an
                    // Impersonate action + <x-impersonate::banner/> blade component.
                    // Consumers wire STS\FilamentImpersonate\Actions\Impersonate into
                    // their UserResource table/header actions manually — this entry
                    // is informational so the plugin appears in the managed catalog.
                    return $panel;
                },
                class: 'STS\\FilamentImpersonate\\FilamentImpersonateServiceProvider',
                package: 'stechstudio/filament-impersonate',
            ),

            new PluginDefinition(
                key: 'filament-uni-file-manager',
                label: 'Uni File Manager',
                installer: function (Panel $panel, array $options): Panel {
                    $class = '\\UniFileManager\\FilamentFileManager\\FilamentFileManagerPlugin';
                    if (class_exists($class)) {
                        $panel->plugin($class::make());
                    }

                    return $panel;
                },
                class: 'UniFileManager\\FilamentFileManager\\FilamentFileManagerPlugin',
                package: 'unifilemanager/filament-file-manager',
            ),

            new PluginDefinition(
                key: 'filament-quick-create',
                label: 'Quick Create',
                installer: function (Panel $panel, array $options): Panel {
                    $class = '\\Awcodes\\QuickCreate\\QuickCreatePlugin';
                    if (! class_exists($class)) {
                        return $panel;
                    }

                    $plugin = $class::make();

                    if (isset($options['excludes']) && is_array($options['excludes']) && method_exists($plugin, 'excludes')) {
                        $plugin->excludes($options['excludes']);
                    }
                    if (isset($options['includes']) && is_array($options['includes']) && method_exists($plugin, 'includes')) {
                        $plugin->includes($options['includes']);
                    }
                    if (isset($options['sort_by']) && method_exists($plugin, 'sortBy')) {
                        $plugin->sortBy($options['sort_by']);
                    }

                    $panel->plugin($plugin);

                    return $panel;
                },
                defaultOptions: [
                    'excludes' => [],
                    'includes' => [],
                    'sort_by' => 'label',
                ],
                class: 'Awcodes\\QuickCreate\\QuickCreatePlugin',
                package: 'awcodes/filament-quick-create',
            ),
        ];
    }

    /**
     * Map a case-insensitive position name (Left/Right/Top/Bottom/Cover) to the
     * vendor's MediaPosition enum case, or null when unset / unresolvable.
     */
    private static function resolveMediaPosition(?string $position): ?object
    {
        $enum = '\\Caresome\\FilamentAuthDesigner\\Enums\\MediaPosition';

        if ($position === null || ! enum_exists($enum)) {
            return null;
        }

        foreach ($enum::cases() as $case) {
            if (strcasecmp($case->name, $position) === 0) {
                return $case;
            }
        }

        return null;
    }
}
