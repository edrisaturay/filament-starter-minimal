<?php

use EdrisaTuray\FilamentStarterMinimal\Models\PanelPluginOverride;
use EdrisaTuray\FilamentStarterMinimal\Support\PluginSyncManager;
use Filament\Panel;
use Filament\PanelRegistry;

beforeEach(function (): void {
    config()->set('filament-starter-minimal.managed_panels', ['admin']);

    // Register a real Filament panel so Filament::getPanels() returns something
    // and sync actually iterates over it. Without this, the legacy version of
    // the test asserted 0 === 0 — a false positive.
    //
    // Registered straight on the registry rather than through
    // Filament::registerPanel(): that facade method only queues a container
    // `resolving` callback for PanelRegistry, and FilamentServiceProvider::boot()
    // has already resolved the singleton by the time this runs, so the callback
    // would never fire and the panel would be silently dropped.
    app(PanelRegistry::class)->register(
        Panel::make()->id('admin')->path('admin'),
    );
});

it('inserts override rows for managed panel × registry plugin pairs', function (): void {
    PluginSyncManager::sync();

    expect(PanelPluginOverride::query()->where('panel_id', 'admin')->count())
        ->toBeGreaterThan(0)
        ->and(PanelPluginOverride::query()->where('panel_id', 'admin')->where('plugin_key', 'filament-shield')->exists())
        ->toBeTrue();
});

it('is idempotent — running twice does not duplicate overrides', function (): void {
    PluginSyncManager::sync();
    $first = PanelPluginOverride::query()->count();

    expect($first)->toBeGreaterThan(0);

    PluginSyncManager::sync();
    $second = PanelPluginOverride::query()->count();

    expect($second)->toBe($first);
});

it('removes overrides whose plugin_key is no longer in the registry', function (): void {
    PanelPluginOverride::create([
        'panel_id' => 'admin',
        'plugin_key' => 'ghost-plugin-no-longer-registered',
        'enabled' => true,
    ]);

    PluginSyncManager::sync();

    expect(PanelPluginOverride::query()->where('plugin_key', 'ghost-plugin-no-longer-registered')->exists())
        ->toBeFalse();
});

it('removes overrides whose panel_id is no longer managed', function (): void {
    PanelPluginOverride::create([
        'panel_id' => 'unmanaged-panel',
        'plugin_key' => 'filament-shield',
        'enabled' => true,
    ]);

    PluginSyncManager::sync();

    expect(PanelPluginOverride::query()->where('panel_id', 'unmanaged-panel')->exists())
        ->toBeFalse();
});
