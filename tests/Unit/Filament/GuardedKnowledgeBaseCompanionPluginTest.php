<?php

use EdrisaTuray\FilamentStarterMinimal\Filament\Plugins\GuardedKnowledgeBaseCompanionPlugin;
use Filament\Panel;
use Filament\PanelRegistry;

/**
 * @return array<string, array<string, array<int, Closure>>>
 */
function panelRenderHooks(Panel $panel): array
{
    return (fn (): array => $this->renderHooks)->call($panel);
}

it('renders none of the companion hooks while the knowledge base panel is not registered', function (): void {
    $panel = Panel::make()->id('admin')->path('admin');

    GuardedKnowledgeBaseCompanionPlugin::make()
        ->knowledgeBasePanelId('knowledge-base')
        ->register($panel);

    $hooks = collect(panelRenderHooks($panel))->flatten();

    expect($hooks)->not->toBeEmpty()
        ->and($hooks->map(fn (Closure $hook): mixed => $hook())->unique()->all())->toBe(['']);
});

it('detects the knowledge base panel once it is registered', function (): void {
    $plugin = GuardedKnowledgeBaseCompanionPlugin::make()->knowledgeBasePanelId('knowledge-base');

    expect($plugin->hasKnowledgeBasePanel())->toBeFalse();

    app(PanelRegistry::class)->register(Panel::make()->id('knowledge-base')->path('kb'));

    expect($plugin->hasKnowledgeBasePanel())->toBeTrue();
});
