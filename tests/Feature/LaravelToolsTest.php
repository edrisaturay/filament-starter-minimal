<?php

use Composer\InstalledVersions;
use EdrisaTuray\FilamentStarterMinimal\Commands\MinimalStarterToolsCommand;
use EdrisaTuray\FilamentStarterMinimal\Http\Middleware\AuthorizeLaravelTool;
use EdrisaTuray\FilamentStarterMinimal\Support\LaravelToolCatalog;
use EdrisaTuray\FilamentStarterMinimal\Support\LaravelToolSetup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpKernel\Exception\HttpException;

trait UsesLaravelToolsProviderMigrations
{
    protected function loadMigrationsFrom(array|string $paths): void
    {
        // RefreshDatabase already runs the migrations registered by the provider.
    }
}

uses(UsesLaravelToolsProviderMigrations::class);

beforeEach(function (): void {
    $this->toolsPath = sys_get_temp_dir().'/starter-tools-test-'.bin2hex(random_bytes(8));
    File::ensureDirectoryExists($this->toolsPath.'/config');
    File::ensureDirectoryExists($this->toolsPath.'/database/migrations');
    $this->app->useConfigPath($this->toolsPath.'/config');
    $this->app->useDatabasePath($this->toolsPath.'/database');
    $this->app->useEnvironmentPath($this->toolsPath);
    File::put($this->toolsPath.'/.env', "QUEUE_CONNECTION=database\nSTRIPE_SECRET=existing-secret\n");
});

afterEach(function (): void {
    File::deleteDirectory($this->toolsPath);
});

it('provides optional monitoring, runtime, and development selections', function (): void {
    $catalog = LaravelToolCatalog::all();
    expect($catalog)->toHaveKeys(['horizon', 'telescope', 'pulse', 'reverb', 'scout', 'pennant', 'sanctum', 'socialite', 'cashier', 'octane', 'sail', 'dusk', 'pint', 'pail'])
        ->and($catalog['dusk']['dev'])->toBeTrue()
        ->and($catalog['horizon']['dev'])->toBeFalse();
    expect(fn () => LaravelToolCatalog::validate(['unknown']))->toThrow(InvalidArgumentException::class);
});

it('leaves tools unselected in unattended installations', function (): void {
    $this->artisan('minimal-starter:tools', ['--no-interaction' => true])
        ->expectsOutput('No optional Laravel tools selected.')
        ->assertSuccessful();
    expect(File::exists(config_path('starter-minimal-tools.php')))->toBeFalse();
});

it('preserves config and previously timestamped migrations when publishing twice', function (): void {
    File::put($this->toolsPath.'/sample.php', '<?php return ["sample" => true];');
    File::put($this->toolsPath.'/2020_01_01_000000_create_sample_table.php', '<?php // migration');
    $provider = new class($this->app) extends ServiceProvider
    {
        public function publishFixtures(string $path): void
        {
            $this->publishes([
                $path.'/sample.php' => config_path('sample.php'),
                $path.'/2020_01_01_000000_create_sample_table.php' => database_path('migrations/2020_01_01_000000_create_sample_table.php'),
            ], 'starter-tools-test-assets');
        }
    };
    $provider->publishFixtures($this->toolsPath);
    File::put(database_path('migrations/2026_01_01_000000_create_sample_table.php'), '<?php // existing');
    $setup = new LaravelToolSetup;
    $setup->publishTag('starter-tools-test-assets');
    File::put(config_path('sample.php'), '<?php return ["custom" => true];');
    $setup->publishTag('starter-tools-test-assets');
    expect(File::get(config_path('sample.php')))->toContain('custom')
        ->and(glob(database_path('migrations/*create_sample_table.php')))->toHaveCount(1);
});

it('fails for unavailable publish tags instead of reporting success', function (): void {
    expect(fn () => (new LaravelToolSetup)->publishTag('missing-starter-tools-test-tag'))
        ->toThrow(RuntimeException::class, 'No publishable resources');
});

it('preserves credentials while applying explicit environment changes once', function (): void {
    $setup = new LaravelToolSetup;
    $setup->environment(['STRIPE_SECRET' => '', 'STRIPE_KEY' => '', 'QUEUE_CONNECTION' => 'redis'], ['QUEUE_CONNECTION']);
    $setup->environment(['STRIPE_SECRET' => '', 'STRIPE_KEY' => '']);
    $contents = File::get($this->toolsPath.'/.env');
    expect($contents)->toContain('STRIPE_SECRET=existing-secret', 'QUEUE_CONNECTION=redis')
        ->and(substr_count($contents, 'STRIPE_KEY='))->toBe(1);
});

it('keeps existing selections and custom settings when adding tools', function (): void {
    config(['starter-minimal-tools.enabled' => ['pulse'], 'starter-minimal-tools.guard' => 'admin']);
    (new LaravelToolSetup)->saveSelections(['pulse', 'horizon']);
    $settings = require config_path('starter-minimal-tools.php');
    expect($settings['enabled'])->toBe(['pulse', 'horizon'])
        ->and($settings['guard'])->toBe('admin');
});

it('rejects guests and ordinary users even in local environments', function (): void {
    $request = Request::create('/horizon');
    $request->setUserResolver(fn () => null);
    expect(fn () => (new AuthorizeLaravelTool)->handle($request, fn () => 'allowed'))->toThrow(HttpException::class);
    $request->setUserResolver(fn () => new class
    {
        public function hasRole(string $role): bool
        {
            return false;
        }
    });
    expect(fn () => (new AuthorizeLaravelTool)->handle($request, fn () => 'allowed'))->toThrow(HttpException::class);
    $request->setUserResolver(fn () => new class
    {
        public function hasRole(string $role): bool
        {
            return $role === 'super_admin';
        }
    });
    expect((new AuthorizeLaravelTool)->handle($request, fn () => 'allowed'))->toBe('allowed');
});

it('stops package discovery and configuration after Composer fails', function (): void {
    $command = new class extends MinimalStarterToolsCommand
    {
        public array $processes = [];

        protected function installPackages(array $keys): void
        {
            throw new RuntimeException('Composer resolution failed');
        }

        public function runProcess(array $arguments): void
        {
            $this->processes[] = $arguments;
        }
    };
    $command->setLaravel($this->app);
    $input = new ArrayInput(['tools' => ['horizon']]);
    $output = new BufferedOutput;
    expect($command->run($input, $output))->toBe(1)
        ->and($command->processes)->toBe([])
        ->and($output->fetch())->toContain('Composer resolution failed');
});

it('configures newly installed packages in a fresh PHP process', function (): void {
    $command = new class extends MinimalStarterToolsCommand
    {
        public array $processes = [];

        protected function installPackages(array $keys): void {}

        public function runProcess(array $arguments): void
        {
            $this->processes[] = $arguments;
        }
    };
    $command->setLaravel($this->app);
    $input = new ArrayInput(['tools' => ['horizon', 'pulse']]);
    expect($command->run($input, new BufferedOutput))->toBe(0)
        ->and($command->processes)->toHaveCount(3)
        ->and($command->processes[2])->toContain('minimal-starter:tools', '--configure', '--no-interaction', 'horizon', 'pulse');
});

it('selects the Reverb driver explicitly for unattended broadcasting setup', function (): void {
    $originalPath = base_path();
    $this->app->setBasePath($this->toolsPath);
    File::ensureDirectoryExists($this->toolsPath.'/routes');
    File::put(config_path('app.php'), '<?php return [];');
    File::put(config_path('broadcasting.php'), '<?php return [];');
    $command = new class extends MinimalStarterToolsCommand
    {
        public array $calls = [];

        public function checkedCall(string $command, array $arguments = []): void
        {
            $this->calls[$command] = $arguments;
        }
    };
    try {
        (new LaravelToolSetup)->configure('reverb', $command);
        expect($command->calls['install:broadcasting'])->toMatchArray([
            '--reverb' => true, '--without-reverb' => true, '--without-node' => true,
        ])->and($command->calls)->toHaveKey('reverb:install')
            ->and(File::get($this->toolsPath.'/.env'))->toContain('BROADCAST_CONNECTION=reverb');
    } finally {
        $this->app->setBasePath($originalPath);
    }
});

it('installs missing Echo dependencies with the existing package manager', function (): void {
    $originalPath = base_path();
    $this->app->setBasePath($this->toolsPath);
    File::put($this->toolsPath.'/package.json', json_encode(['dependencies' => ['vue' => '^3.0', 'pusher-js' => '^8.0']]));
    File::put($this->toolsPath.'/pnpm-lock.yaml', '');
    $command = new class extends MinimalStarterToolsCommand
    {
        public array $processes = [];

        public function runProcess(array $arguments): void
        {
            $this->processes[] = $arguments;
        }
    };
    $setup = new class extends LaravelToolSetup
    {
        public function echoDependencies(MinimalStarterToolsCommand $command): void
        {
            $this->setupEcho($command);
        }
    };
    try {
        $setup->echoDependencies($command);
        expect($command->processes)->toBe([['pnpm', 'add', '--save-dev', 'laravel-echo', '@laravel/echo-vue']]);
    } finally {
        $this->app->setBasePath($originalPath);
    }
});

it('allows Composer to resolve locked dependencies when installing tools', function (): void {
    $command = new class extends MinimalStarterToolsCommand
    {
        public array $processes = [];

        public function installSelection(array $keys): void
        {
            $this->installPackages($keys);
        }

        public function runProcess(array $arguments): void
        {
            $this->processes[] = $arguments;
        }
    };
    $keys = array_keys(array_filter(LaravelToolCatalog::all(), fn (array $tool): bool => ! InstalledVersions::isInstalled($tool['package'])));
    expect($keys)->not->toBeEmpty();
    $command->installSelection($keys);
    expect($command->processes)->not->toBeEmpty();
    foreach ($command->processes as $arguments) {
        expect($arguments)->toContain('require', '--with-all-dependencies', '--no-scripts', '--no-interaction');
    }
});
