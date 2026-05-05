<?php

namespace EdrisaTuray\FilamentStarterMinimal\Commands;

use EdrisaTuray\FilamentStarterMinimal\Support\MinimalDoctor;
use Illuminate\Console\Command;

class MinimalStarterDoctorCommand extends Command
{
    protected $signature = 'minimal-starter:doctor';

    protected $description = 'Run health checks for Filament Starter Minimal';

    public function handle(MinimalDoctor $doctor): int
    {
        $this->info('Filament Starter Minimal — doctor');

        $exitCode = self::SUCCESS;

        foreach ($doctor->check() as $result) {
            $statusLabel = strtoupper($result['status']);
            $color = match ($result['status']) {
                'ok' => 'info',
                'warning' => 'comment',
                'critical' => 'error',
                default => 'info',
            };

            $this->line("<{$color}>[{$statusLabel}]</{$color}> {$result['check']}: {$result['message']}");

            if ($result['status'] === 'critical') {
                $exitCode = self::FAILURE;
            }
        }

        return $exitCode;
    }
}
