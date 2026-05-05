<?php

namespace EdrisaTuray\FilamentStarterMinimal\Commands;

use EdrisaTuray\FilamentStarterMinimal\Support\PanelSnapshotManager;
use Illuminate\Console\Command;

class MinimalStarterInstallCommand extends Command
{
    protected $signature = 'minimal-starter:install
                            {--publish-config : Publish filament-starter-minimal.php to the config directory}
                            {--no-interaction : Do not prompt for optional steps}';

    protected $description = 'Publish config (optional), migrate, and sync panel snapshots / plugin overrides for Filament Starter Minimal';

    public function handle(): int
    {
        $this->info('Filament Starter Minimal — install');

        try {
            if ($this->option('publish-config') || (! $this->option('no-interaction') && $this->confirm('Publish config/filament-starter-minimal.php?', false))) {
                $this->call('vendor:publish', [
                    '--tag' => 'filament-starter-minimal-config',
                    '--force' => true,
                ]);
            }

            $this->info('Running migrations...');
            $migrateExit = $this->call('migrate', ['--force' => true]);
            if ($migrateExit !== 0) {
                $this->error('migrate failed with exit code '.$migrateExit);

                return self::FAILURE;
            }

            $this->info('Syncing panel snapshots and plugin registry rows...');
            PanelSnapshotManager::snapshot();

            $this->info('minimal-starter:install completed.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            report($e);
            $this->error('minimal-starter:install failed: '.$e->getMessage());
            $this->line('Run `php artisan minimal-starter:doctor` for diagnostics.');

            return self::FAILURE;
        }
    }
}
