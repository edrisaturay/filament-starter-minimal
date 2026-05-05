<?php

namespace EdrisaTuray\FilamentStarterMinimal\Commands;

use EdrisaTuray\FilamentStarterMinimal\Support\MinimalSafeMode;
use EdrisaTuray\FilamentStarterMinimal\Support\PluginStateResolver;
use Filament\Facades\Filament;
use Illuminate\Console\Command;

class MinimalStarterSafeModeCommand extends Command
{
    protected $signature = 'minimal-starter:safe-mode {status : on or off}';

    protected $description = 'Toggle safe mode for Filament Starter Minimal (forces dangerous plugins on)';

    public function handle(): int
    {
        $status = strtolower((string) $this->argument('status'));

        if ($status === 'on') {
            MinimalSafeMode::activate();
            $this->invalidateAllPanels();
            $this->info('Minimal safe mode activated (cache flag).');

            return self::SUCCESS;
        }

        if ($status === 'off') {
            MinimalSafeMode::deactivate();
            $this->invalidateAllPanels();
            $this->info('Minimal safe mode deactivated.');

            return self::SUCCESS;
        }

        $this->error('Invalid status. Use "on" or "off".');

        return self::FAILURE;
    }

    protected function invalidateAllPanels(): void
    {
        foreach (Filament::getPanels() as $panelId => $panel) {
            PluginStateResolver::invalidate($panelId);
        }
    }
}
