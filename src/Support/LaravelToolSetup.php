<?php

namespace EdrisaTuray\FilamentStarterMinimal\Support;

use EdrisaTuray\FilamentStarterMinimal\Commands\MinimalStarterToolsCommand;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class LaravelToolSetup
{
    public function configure(string $key, MinimalStarterToolsCommand $command): void
    {
        LaravelToolCatalog::validate([$key]);
        $tags = match ($key) {
            'horizon' => ['horizon-config'],
            'telescope' => ['telescope-config', 'telescope-migrations'],
            'pulse' => ['pulse-config', 'pulse-dashboard', 'pulse-migrations'],
            'pennant' => ['pennant-config', 'pennant-migrations'],
            'sanctum' => ['sanctum-config', 'sanctum-migrations'],
            'cashier' => ['cashier-config', 'cashier-migrations'],
            default => [],
        };
        foreach ($tags as $tag) {
            $this->publishTag($tag);
        }

        match ($key) {
            'horizon' => $this->setupHorizon(),
            'telescope' => $this->environment(['TELESCOPE_ENABLED' => 'true']),
            'scout' => $this->setupScout(),
            'sanctum' => $this->addUserTrait('Laravel\\Sanctum\\HasApiTokens'),
            'cashier' => $this->setupCashier(),
            'socialite' => $this->setupSocialite(),
            'reverb' => $this->setupReverb($command),
            'octane' => $this->setupOctane($command),
            'sail' => $this->setupSail($command),
            'dusk' => $this->setupDusk($command),
            'pint' => $this->writeIfMissing(base_path('pint.json'), "{\n    \"preset\": \"laravel\"\n}\n"),
            default => null,
        };
    }

    public function publishTag(string $tag): void
    {
        $paths = ServiceProvider::pathsToPublish(null, $tag);
        if ($paths === []) {
            throw new RuntimeException("No publishable resources found for [{$tag}]. Check package discovery and version compatibility.");
        }
        foreach ($paths as $source => $destination) {
            $this->publishPath($source, $destination);
        }
    }

    protected function publishPath(string $source, string $destination): void
    {
        $files = new Filesystem;
        if ($files->isDirectory($source)) {
            foreach ($files->allFiles($source) as $file) {
                $this->publishPath($file->getPathname(), $destination.'/'.$file->getRelativePathname());
            }

            return;
        }
        if (! $files->isFile($source)) {
            throw new RuntimeException("Missing publish source [{$source}].");
        }
        if (str_contains($destination, DIRECTORY_SEPARATOR.'migrations'.DIRECTORY_SEPARATOR)) {
            $suffix = preg_replace('/^\d{4}_\d{2}_\d{2}_\d{6}_/', '', basename($destination));
            if (glob(dirname($destination).'/*_'.$suffix)) {
                return;
            }
        }
        $this->writeIfMissing($destination, $files->get($source));
    }

    protected function setupHorizon(): void
    {
        $this->environment(['QUEUE_CONNECTION' => 'redis'], replace: ['QUEUE_CONNECTION']);
        if (! extension_loaded('redis')) {
            if (! class_exists('Predis\\Client')) {
                throw new RuntimeException('Horizon requires the Redis extension or predis/predis. Rerun setup without --configure to install the client.');
            }
            $this->environment(['REDIS_CLIENT' => 'predis'], replace: ['REDIS_CLIENT']);
        }
    }

    protected function setupScout(): void
    {
        $provider = 'Laravel\\Scout\\ScoutServiceProvider';
        $paths = ServiceProvider::pathsToPublish($provider);
        if ($paths === []) {
            throw new RuntimeException('Scout configuration could not be found.');
        }
        foreach ($paths as $source => $destination) {
            $this->publishPath($source, $destination);
        }
        $this->environment(['SCOUT_DRIVER' => 'database']);
    }

    protected function setupCashier(): void
    {
        $this->addUserTrait('Laravel\\Cashier\\Billable');
        $this->environment(['STRIPE_KEY' => '', 'STRIPE_SECRET' => '', 'STRIPE_WEBHOOK_SECRET' => '']);
    }

    protected function setupSocialite(): void
    {
        $source = __DIR__.'/../../stubs/laravel-tools/socialite.php';
        $this->writeIfMissing(config_path('starter-minimal-socialite.php'), file_get_contents($source));
        $this->environment(['GITHUB_CLIENT_ID' => '', 'GITHUB_CLIENT_SECRET' => '', 'GITHUB_REDIRECT_URI' => '',
            'GOOGLE_CLIENT_ID' => '', 'GOOGLE_CLIENT_SECRET' => '', 'GOOGLE_REDIRECT_URI' => '']);
    }

    protected function setupReverb(MinimalStarterToolsCommand $command): void
    {
        foreach (['broadcasting', 'app'] as $config) {
            if (! is_file(config_path($config.'.php'))) {
                $command->checkedCall('config:publish', ['name' => $config]);
            }
        }
        if (! is_file(base_path('routes/channels.php'))) {
            $command->checkedCall('install:broadcasting', ['--reverb' => true, '--without-reverb' => true, '--without-node' => true]);
        }
        $command->checkedCall('reverb:install');
        $this->environment(['BROADCAST_CONNECTION' => 'reverb'], replace: ['BROADCAST_CONNECTION']);
        $this->setupEcho($command);
    }

    protected function setupEcho(MinimalStarterToolsCommand $command): void
    {
        if (! is_file(base_path('package.json'))) {
            return;
        }
        $manifest = json_decode(file_get_contents(base_path('package.json')), true, flags: JSON_THROW_ON_ERROR);
        $dependencies = array_merge($manifest['dependencies'] ?? [], $manifest['devDependencies'] ?? []);
        $packages = ['laravel-echo', 'pusher-js'];
        foreach (['vue' => '@laravel/echo-vue', 'react' => '@laravel/echo-react'] as $framework => $package) {
            if (isset($dependencies[$framework])) {
                $packages[] = $package;
            }
        }
        $missing = array_values(array_filter($packages, fn (string $package): bool => ! isset($dependencies[$package])));
        if ($missing === []) {
            return;
        }
        $manager = match (true) {
            is_file(base_path('pnpm-lock.yaml')) => 'pnpm',
            is_file(base_path('yarn.lock')) => 'yarn',
            is_file(base_path('bun.lock')), is_file(base_path('bun.lockb')) => 'bun',
            default => 'npm',
        };
        $command->runProcess([$manager, $manager === 'npm' ? 'install' : 'add', '--save-dev', ...$missing]);
    }

    protected function setupOctane(MinimalStarterToolsCommand $command): void
    {
        if (! is_file(config_path('octane.php'))) {
            $command->checkedCall('octane:install', ['--server' => 'frankenphp']);
        }
    }

    protected function setupSail(MinimalStarterToolsCommand $command): void
    {
        foreach (['compose.yaml', 'compose.yml', 'docker-compose.yml', 'docker-compose.yaml'] as $file) {
            if (is_file(base_path($file))) {
                return;
            }
        }
        $command->checkedCall('sail:install', ['--with' => 'redis', '--php' => PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION]);
    }

    protected function setupDusk(MinimalStarterToolsCommand $command): void
    {
        if (! is_file(base_path('tests/DuskTestCase.php'))) {
            $command->checkedCall('dusk:install');
        }
    }

    protected function addUserTrait(string $trait): void
    {
        $model = config('filament-starter-minimal.users.model') ?: config('auth.providers.users.model');
        if (! is_string($model) || ! class_exists($model)) {
            throw new RuntimeException('A valid user model is required for the selected Laravel tool.');
        }
        app(UserModelRolesSetup::class)->ensureTrait($model, $trait);
    }

    public function environment(array $values, array $replace = []): void
    {
        $path = app()->environmentFilePath();
        if (! is_file($path)) {
            throw new RuntimeException('Create the application .env file before configuring Laravel tools.');
        }
        $files = new Filesystem;
        $contents = $files->get($path);
        foreach ($values as $key => $value) {
            $pattern = '/^'.preg_quote($key, '/').'=.*/m';
            if (preg_match($pattern, $contents)) {
                if (in_array($key, $replace, true)) {
                    $contents = preg_replace_callback($pattern, fn (): string => $key.'='.$value, $contents);
                }
            } else {
                $contents = rtrim($contents).PHP_EOL.$key.'='.$value.PHP_EOL;
            }
        }
        $files->replace($path, $contents);
    }

    public function saveSelections(array $keys): void
    {
        $settings = config('starter-minimal-tools', []);
        $settings['enabled'] = array_values(array_unique(array_merge($settings['enabled'] ?? [], $keys)));
        $files = new Filesystem;
        $files->ensureDirectoryExists(config_path());
        $files->replace(config_path('starter-minimal-tools.php'), '<?php'.PHP_EOL.PHP_EOL.'return '.var_export($settings, true).';'.PHP_EOL);
    }

    protected function writeIfMissing(string $path, string $contents): void
    {
        $files = new Filesystem;
        if ($files->exists($path)) {
            return;
        }
        $files->ensureDirectoryExists(dirname($path));
        $files->put($path, $contents);
    }

    public function requirements(array $keys): array
    {
        $messages = [
            'horizon' => 'Horizon requires a running Redis server and a supervised `php artisan horizon` process. QUEUE_CONNECTION is now redis.',
            'telescope' => 'Telescope pruning is scheduled automatically. Your server must run Laravel scheduler cron.',
            'pulse' => 'For Pulse server metrics, supervise `php artisan pulse:check`. Dashboard storage uses your configured database.',
            'reverb' => 'Run `php artisan reverb:start` under a process supervisor and set the public Reverb host/TLS settings. Echo dependencies are installed when package.json exists. Build your frontend assets before deployment.',
            'scout' => 'Scout is configured with the database driver when no driver was set. Add Searchable to the application models you want to index.',
            'socialite' => 'Socialite provider settings are prepared for GitHub and Google. Supply OAuth credentials and implement your application login/callback flow.',
            'cashier' => 'Cashier Stripe configuration, migrations, and the user Billable trait are prepared. Supply Stripe keys, configure the webhook URL, and implement your product/checkout flow.',
            'octane' => 'Octane uses FrankenPHP for a new installation. Supervise `php artisan octane:start --server=frankenphp` in production.',
            'sail' => 'Sail scaffolding includes Redis and preserves your existing database choice. Start Docker and run `vendor/bin/sail up -d`.',
            'dusk' => 'Dusk scaffolding is ready. Browser tests require Chrome and a running application.',
        ];

        return array_values(array_intersect_key($messages, array_flip($keys)));
    }
}
