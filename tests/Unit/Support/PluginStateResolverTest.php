<?php

use EdrisaTuray\FilamentStarterMinimal\Contracts\PluginRegistryContract;
use EdrisaTuray\FilamentStarterMinimal\Models\PanelPluginOverride;
use EdrisaTuray\FilamentStarterMinimal\Registry\PluginDefinition;
use EdrisaTuray\FilamentStarterMinimal\Support\PluginStateResolver;
use Filament\Panel;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    app(PluginRegistryContract::class)->register(new PluginDefinition(
        key: 'test-plugin',
        label: 'Test Plugin',
        installer: fn (Panel $panel, array $options): Panel => $panel,
        defaultEnabled: true,
        defaultOptions: ['navigation' => true, 'label' => 'Default'],
    ));
});

function stateCacheKey(string $panelId): string
{
    return "starter_minimal_plugins_{$panelId}_v".app(PluginRegistryContract::class)->signature();
}

it('keeps registry option defaults that an override row does not set', function (): void {
    PanelPluginOverride::query()->create([
        'panel_id' => 'admin',
        'plugin_key' => 'test-plugin',
        'enabled' => true,
        'options' => [],
    ]);

    expect(PluginStateResolver::resolve('admin')['test-plugin']['options'])
        ->toBe(['navigation' => true, 'label' => 'Default']);
});

it('lets an override row replace individual option keys', function (): void {
    PanelPluginOverride::query()->create([
        'panel_id' => 'admin',
        'plugin_key' => 'test-plugin',
        'enabled' => true,
        'options' => ['label' => 'Custom'],
    ]);

    expect(PluginStateResolver::resolve('admin')['test-plugin']['options'])
        ->toBe(['navigation' => true, 'label' => 'Custom']);
});

it('reflects a toggled override on the next resolve', function (): void {
    $override = PanelPluginOverride::query()->create([
        'panel_id' => 'admin',
        'plugin_key' => 'test-plugin',
        'enabled' => true,
    ]);

    expect(PluginStateResolver::resolve('admin')['test-plugin']['enabled'])->toBeTrue();

    $override->update(['enabled' => false]);

    expect(PluginStateResolver::resolve('admin')['test-plugin']['enabled'])->toBeFalse();
});

it('does not cache state while the override table is missing', function (): void {
    // An unmigrated database, as when panels boot before `migrate` has run.
    config()->set('database.connections.unmigrated', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
    ]);
    config()->set('database.default', 'unmigrated');

    $state = PluginStateResolver::resolve('admin');

    config()->set('database.default', 'testing');

    expect($state['test-plugin']['enabled'])->toBeTrue()
        ->and(Cache::has(stateCacheKey('admin')))->toBeFalse();
});

it('caches state once the override table is readable', function (): void {
    PluginStateResolver::resolve('admin');

    expect(Cache::has(stateCacheKey('admin')))->toBeTrue();
});

it('changes the registry signature when default options change', function (): void {
    $registry = app(PluginRegistryContract::class);
    $before = $registry->signature();

    $registry->register(new PluginDefinition(
        key: 'test-plugin',
        label: 'Test Plugin',
        installer: fn (Panel $panel, array $options): Panel => $panel,
        defaultEnabled: true,
        defaultOptions: ['navigation' => false, 'label' => 'Default'],
    ));

    expect($registry->signature())->not->toBe($before);
});
