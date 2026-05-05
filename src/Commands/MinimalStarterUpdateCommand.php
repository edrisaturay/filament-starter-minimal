<?php

namespace EdrisaTuray\FilamentStarterMinimal\Commands;

use EdrisaTuray\FilamentStarterMinimal\Support\PanelSnapshotManager;
use Illuminate\Console\Command;

class MinimalStarterUpdateCommand extends Command
{
    protected $signature = 'minimal-starter:update';

    protected $description = 'Run migrations and refresh panel snapshots / plugin overrides for Filament Starter Minimal';

    public function handle(): int
    {
        $this->info('Filament Starter Minimal — update');

        try {
            $migrateExit = $this->call('migrate', ['--force' => true]);
            if ($migrateExit !== 0) {
                $this->error('migrate failed with exit code '.$migrateExit);

                return self::FAILURE;
            }

            PanelSnapshotManager::snapshot();

            $this->info('minimal-starter:update completed.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            report($e);
            $this->error('minimal-starter:update failed: '.$e->getMessage());
            $this->line('Run `php artisan minimal-starter:doctor` for diagnostics.');

            return self::FAILURE;
        }
    }
}
