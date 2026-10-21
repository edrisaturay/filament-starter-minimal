<?php

use EdrisaTuray\FilamentStarterMinimal\Contracts\PluginRegistryContract;
use EdrisaTuray\FilamentStarterMinimal\Filament\Plugins\GuardedKnowledgeBaseCompanionPlugin;
use EdrisaTuray\FilamentStarterMinimal\Support\PluginStateResolver;
use Filament\Panel;
use Guava\FilamentKnowledgeBase\Plugins\KnowledgeBasePlugin;

trait UsesKnowledgeBaseProviderMigrations
{
    protected function loadMigrationsFrom(array|string $paths): void
    {
        // RefreshDatabase already runs provider migrations without a second rollback.
    }
}

uses(UsesKnowledgeBaseProviderMigrations::class);

it('exposes one knowledge base choice in the plugin registry', function (): void {
    $registry = app(PluginRegistryContract::class);
    $entries = array_filter($registry->all(), fn ($definition): bool => $definition->package === 'guava/filament-knowledge-base');
    expect(array_keys($entries))->toBe(['filament-knowledge-base'])
        ->and($registry->get('filament-knowledge-base')->label)->toBe('Knowledge Base');
});

it('installs documentation on the configured knowledge base panel', function (): void {
    $definition = app(PluginRegistryContract::class)->get('filament-knowledge-base');
    $panel = Panel::make()->id('docs')->path('docs');
    ($definition->installer)($panel, ['knowledge_base_panel_id' => 'docs']);
    expect($panel->getPlugins())->toHaveKey(KnowledgeBasePlugin::ID)
        ->not->toHaveKey(GuardedKnowledgeBaseCompanionPlugin::ID);
});

it('installs the companion on regular panels using the same choice', function (): void {
    config(['filament-starter-minimal.managed_panels' => ['admin', 'docs']]);
    PluginStateResolver::invalidate('docs');
    $definition = app(PluginRegistryContract::class)->get('filament-knowledge-base');
    $panel = Panel::make()->id('admin')->path('admin');
    ($definition->installer)($panel, ['knowledge_base_panel_id' => 'docs', 'modal_previews' => true]);
    expect($panel->getPlugin(GuardedKnowledgeBaseCompanionPlugin::ID))->toBeInstanceOf(GuardedKnowledgeBaseCompanionPlugin::class)
        ->and($panel->getPlugin(GuardedKnowledgeBaseCompanionPlugin::ID)->getKnowledgeBasePanelId())->toBe('docs')
        ->and($panel->getPlugins())->not->toHaveKey(KnowledgeBasePlugin::ID);
});

it('skips the companion when documentation is unmanaged or disabled', function (bool $managed): void {
    config([
        'filament-starter-minimal.managed_panels' => $managed ? ['admin', 'docs'] : ['admin'],
        'filament-starter-minimal.plugin_defaults.docs.filament-knowledge-base.enabled' => false,
    ]);
    PluginStateResolver::invalidate('docs');
    $definition = app(PluginRegistryContract::class)->get('filament-knowledge-base');
    $panel = Panel::make()->id('admin')->path('admin');
    ($definition->installer)($panel, ['knowledge_base_panel_id' => 'docs']);
    expect($panel->getPlugins())->toBe([]);
})->with([true, false]);
