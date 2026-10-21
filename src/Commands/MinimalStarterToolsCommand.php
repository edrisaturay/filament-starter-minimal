<?php

namespace EdrisaTuray\FilamentStarterMinimal\Commands;

use Composer\InstalledVersions;
use EdrisaTuray\FilamentStarterMinimal\Support\LaravelToolCatalog;
use EdrisaTuray\FilamentStarterMinimal\Support\LaravelToolSetup;
use Illuminate\Console\Command;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

use function Laravel\Prompts\multiselect;

class MinimalStarterToolsCommand extends Command
{
    protected $signature = 'minimal-starter:tools {tools?* : Optional Laravel tool keys}
        {--configure : Configure already installed packages in a fresh process}';

    protected $description = 'Install and configure selected optional Laravel ecosystem tools.';

    public function handle(LaravelToolSetup $setup): int
    {
        try {
            $keys = LaravelToolCatalog::validate($this->argument('tools'));
            if ($keys === [] && $this->input->isInteractive()) {
                $keys = multiselect('Which optional Laravel tools should be installed?',
                    collect(LaravelToolCatalog::all())->mapWithKeys(fn (array $tool, string $key): array => [$key => $tool['label']])->all(),
                    default: config('starter-minimal-tools.enabled', []));
            }
            if ($keys === []) {
                $this->line('No optional Laravel tools selected.');

                return self::SUCCESS;
            }

            if ($this->option('configure')) {
                foreach ($keys as $key) {
                    if (! InstalledVersions::isInstalled(LaravelToolCatalog::all()[$key]['package'])) {
                        throw new RuntimeException("Package for [{$key}] is not installed. Run minimal-starter:tools {$key} first.");
                    }
                    $this->info("Setting up {$key}...");
                    $setup->configure($key, $this);
                }
                $this->checkedCall('migrate', ['--force' => true]);
                $setup->saveSelections($keys);
                $this->checkedCall('config:clear');
                $this->info('Selected Laravel packages configured and migrations applied.');
                foreach ($setup->requirements($keys) as $requirement) {
                    $this->warn($requirement);
                }

                return self::SUCCESS;
            }

            $this->installPackages($keys);
            $this->runProcess([PHP_BINARY, base_path('artisan'), 'package:discover', '--no-interaction']);
            $this->runProcess([PHP_BINARY, base_path('artisan'), 'config:clear', '--no-interaction']);
            $this->runProcess([PHP_BINARY, '-d', 'opcache.enable_cli=0', base_path('artisan'), 'minimal-starter:tools', ...$keys, '--configure', '--no-interaction']);

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            report($exception);
            $this->error('Laravel tool setup failed: '.$exception->getMessage());

            return self::FAILURE;
        }
    }

    protected function installPackages(array $keys): void
    {
        $composer = (new ExecutableFinder)->find('composer');
        foreach ([false, true] as $dev) {
            $packages = [];
            foreach ($keys as $key) {
                $tool = LaravelToolCatalog::all()[$key];
                if ($tool['dev'] === $dev && ! InstalledVersions::isInstalled($tool['package'])) {
                    $packages[] = $tool['package'];
                }
            }
            if (! $dev && in_array('horizon', $keys, true) && ! extension_loaded('redis') && ! InstalledVersions::isInstalled('predis/predis')) {
                $packages[] = 'predis/predis';
            }
            if ($packages === []) {
                continue;
            }
            if ($composer === null) {
                throw new RuntimeException('Composer must be available on PATH to install selected Laravel tools.');
            }
            $arguments = [$composer, 'require', ...$packages, '--no-interaction', '--no-scripts'];
            if ($dev) {
                $arguments[] = '--dev';
            }
            $this->runProcess($arguments);
        }
    }

    public function runProcess(array $arguments): void
    {
        $process = new Process($arguments, base_path());
        $process->setTimeout(null);
        $exit = $process->run(fn (string $type, string $buffer) => $this->output->write($buffer));
        if ($exit !== 0) {
            throw new RuntimeException('A Laravel tool setup subprocess failed with exit code '.$exit.'. Resolve the reported error and rerun the same selection.');
        }
    }

    public function checkedCall(string $command, array $arguments = []): void
    {
        if ($this->call($command, $arguments + ['--no-interaction' => true]) !== self::SUCCESS) {
            throw new RuntimeException("Command [{$command}] failed.");
        }
    }
}
