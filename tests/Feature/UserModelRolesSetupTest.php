<?php

use EdrisaTuray\FilamentStarterMinimal\Commands\MinimalStarterInstallCommand;
use EdrisaTuray\FilamentStarterMinimal\Support\UserModelRolesSetup;
use Illuminate\Support\Facades\File;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Process\Process;

trait UsesRoleSetupProviderMigrations
{
    protected function loadMigrationsFrom(array|string $paths): void
    {
        // RefreshDatabase already runs the migrations registered by the provider.
    }
}

uses(UsesRoleSetupProviderMigrations::class);

it('adds HasRoles without changing existing model members and works in a fresh process', function (): void {
    $class = 'SetupUser'.bin2hex(random_bytes(6));
    $path = sys_get_temp_dir().'/'.$class.'.php';
    $source = "<?php\nnamespace InstallerFixtures;\nclass {$class} extends \\Illuminate\\Foundation\\Auth\\User\n{\n    protected \$table = 'users';\n}\n";
    File::put($path, $source);

    try {
        require $path;
        $model = 'InstallerFixtures\\'.$class;
        $setup = new UserModelRolesSetup;
        expect($setup->ensure($model))->toBeTrue();
        $updated = File::get($path);
        expect($updated)->toContain('use \\Spatie\\Permission\\Traits\\HasRoles;')
            ->and(str_replace("\n    use \\Spatie\\Permission\\Traits\\HasRoles;\n", '', $updated))->toBe($source);
        expect($setup->ensure($model))->toBeTrue()
            ->and(File::get($path))->toBe($updated);
        $process = new Process([PHP_BINARY, '-r',
            'require $argv[1]; require $argv[2]; exit(method_exists($argv[3], "assignRole") && method_exists($argv[3], "hasRole") ? 0 : 1);',
            __DIR__.'/../../vendor/autoload.php', $path, $model,
        ]);
        $process->run();
        expect($process->getExitCode())->toBe(0, $process->getErrorOutput());
    } finally {
        File::delete($path);
    }
});

it('leaves models with inherited role support untouched', function (): void {
    $class = 'ExistingRoleUser'.bin2hex(random_bytes(6));
    $path = sys_get_temp_dir().'/'.$class.'.php';
    File::put($path, "<?php\nclass {$class} extends \\Illuminate\\Foundation\\Auth\\User { use \\Spatie\\Permission\\Traits\\HasRoles; }\nclass {$class}Child extends {$class} {}\n");
    try {
        require $path;
        $original = File::get($path);
        expect((new UserModelRolesSetup)->ensure($class.'Child'))->toBeFalse()
            ->and(File::get($path))->toBe($original);
    } finally {
        File::delete($path);
    }
});

it('fails clearly for a missing model', function (): void {
    expect(fn () => (new UserModelRolesSetup)->ensure('MissingInstallerUser'))
        ->toThrow(RuntimeException::class, 'does not exist');
});

it('restarts before publishing or migrating and propagates the child exit code', function (): void {
    $setup = Mockery::mock(UserModelRolesSetup::class);
    $setup->shouldReceive('ensure')->once()->with('App\\Models\\User')->andReturn(true);
    $this->app->instance(UserModelRolesSetup::class, $setup);
    config(['auth.providers.users.model' => 'App\\Models\\User']);
    $command = new class extends MinimalStarterInstallCommand
    {
        protected function restartInstallation(): int
        {
            return 7;
        }

        protected function publishOwnConfig(): void
        {
            throw new RuntimeException('The stale parent process must not continue installing.');
        }
    };
    $command->setLaravel($this->app);
    $output = new BufferedOutput;
    $exit = $command->run(new ArrayInput([]), $output);
    expect($exit)->toBe(7)
        ->and($output->fetch())->toContain('Restarting installation');
});
