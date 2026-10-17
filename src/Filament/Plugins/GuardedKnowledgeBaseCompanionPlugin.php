<?php

namespace EdrisaTuray\FilamentStarterMinimal\Filament\Plugins;

use Filament\Facades\Filament;
use Filament\Panel;
use Guava\FilamentKnowledgeBase\Plugins\KnowledgeBaseCompanionPlugin;

/**
 * Knowledge Base companion that renders nothing while its target KB panel is
 * not registered with Filament.
 *
 * The vendor companion's render hooks call Filament::getPanel($kbPanelId)
 * unconditionally, so a missing KB panel crashes every page of the host panel
 * ("getPlugin() on null"). Whether that panel exists cannot be checked while
 * panels are still being built (asking the registry from inside a panel
 * definition re-enters its resolution), so the vendor hooks are collected on a
 * scratch panel and re-registered behind a render-time check instead.
 */
class GuardedKnowledgeBaseCompanionPlugin extends KnowledgeBaseCompanionPlugin
{
    public function register(Panel $panel): void
    {
        $collector = new class extends Panel
        {
            /**
             * @return array<string, array<string, array<int, \Closure>>>
             */
            public function collectedRenderHooks(): array
            {
                return $this->renderHooks;
            }
        };

        parent::register($collector);

        foreach ($collector->collectedRenderHooks() as $name => $scopedHooks) {
            foreach ($scopedHooks as $scope => $hooks) {
                foreach ($hooks as $hook) {
                    $panel->renderHook(
                        $name,
                        fn (): mixed => $this->hasKnowledgeBasePanel() ? app()->call($hook) : '',
                        $scope === '' ? null : $scope,
                    );
                }
            }
        }
    }

    public function hasKnowledgeBasePanel(): bool
    {
        return Filament::getPanel($this->getKnowledgeBasePanelId()) !== null;
    }
}
