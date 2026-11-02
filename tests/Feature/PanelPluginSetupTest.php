<?php

use EdrisaTuray\FilamentStarterMinimal\Commands\MinimalStarterInstallCommand;
use EdrisaTuray\FilamentStarterMinimal\Support\PanelPluginSetup;
use EdrisaTuray\FilamentStarterMinimal\Support\UserModelRolesSetup;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Support\Facades\File;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Process\Process;

trait UsesPanelSetupProviderMigrations
{
    protected function loadMigrationsFrom(array|string $paths): void {}
}

uses(UsesPanelSetupProviderMigrations::class);

it('registers the plugin while preserving the existing panel definition', function (): void {
    $class = 'SetupPanel'.bin2hex(random_bytes(6));
    $path = sys_get_temp_dir().'/'.$class.'.php';
    $source = "<?php\nclass {$class} extends \\Filament\\PanelProvider\n{\n    public function panel(\\Filament\\Panel \$panel): \\Filament\\Panel\n    {\n        return \$panel->id('staff')->path('team')->login();\n    }\n}\n";
    File::put($path, $source);
    try {
        require $path;
        (new PanelPluginSetup)->registerInProvider($class);
        expect(File::get($path))->toContain("->id('staff')->path('team')->login()", 'FilamentStarterMinimalPlugin::make()');
        // A small plugin double isolates the source transformation from package bootstrapping.
        $script = <<<'SCRIPT'
require $argv[1];
eval('namespace EdrisaTuray\\FilamentStarterMinimal\\Filament; class FilamentStarterMinimalPlugin implements \\Filament\\Contracts\\Plugin { public static function make(): static { return new static; } public function getId(): string { return "filament-starter-minimal"; } public function register(\\Filament\\Panel $panel): void {} public function boot(\\Filament\\Panel $panel): void {} }');
$app = new Illuminate\Foundation\Application;
Illuminate\Container\Container::setInstance($app);
require $argv[2];
$provider = new $argv[3]($app);
$panel = $provider->panel(Filament\Panel::make());
exit($panel->getId() === 'staff' && $panel->getPath() === 'team' && $panel->hasPlugin('filament-starter-minimal') ? 0 : 1);
SCRIPT;
        $process = new Process([PHP_BINARY, '-r', $script, __DIR__.'/../../vendor/autoload.php', $path, $class]);
        $process->run();
        expect($process->getExitCode())->toBe(0, $process->getErrorOutput().$process->getOutput());
        $updated = File::get($path);
        expect(fn () => (new PanelPluginSetup)->registerInProvider($class))->toThrow(RuntimeException::class, 'already contains');
        expect(File::get($path))->toBe($updated);
    } finally {
        File::delete($path);
    }
});

it('does not rewrite panels that already have the starter plugin', function (): void {
    $panel = Mockery::mock(Panel::class);
    $panel->shouldReceive('hasPlugin')->once()->with('filament-starter-minimal')->andReturn(true);
    Filament::shouldReceive('getPanels')->once()->andReturn(['admin' => $panel]);
    expect((new PanelPluginSetup)->ensure())->toBeFalse();
});

it('fails clearly when a configured panel is not registered', function (): void {
    Filament::shouldReceive('getPanels')->once()->andReturn([]);
    expect(fn () => (new PanelPluginSetup)->ensure())->toThrow(RuntimeException::class, 'Managed panel [admin] is not registered');
});

it('leaves providers with conditional returns untouched', function (): void {
    $class = 'CustomPanel'.bin2hex(random_bytes(6));
    $path = sys_get_temp_dir().'/'.$class.'.php';
    $source = "<?php\nclass {$class} extends \\Filament\\PanelProvider\n{\n    public function panel(\\Filament\\Panel \$panel): \\Filament\\Panel\n    {\n        if (true) { return \$panel->id('admin'); }\n        return \$panel->id('staff');\n    }\n}\n";
    File::put($path, $source);
    try {
        require $path;
        expect(fn () => (new PanelPluginSetup)->registerInProvider($class))->toThrow(RuntimeException::class, 'Cannot safely update');
        expect(File::get($path))->toBe($source);
    } finally {
        File::delete($path);
    }
});

it('restarts after panel registration before publishing and propagates failures', function (): void {
    $roles = Mockery::mock(UserModelRolesSetup::class);
    $roles->shouldReceive('ensure')->once()->andReturn(false);
    $this->app->instance(UserModelRolesSetup::class, $roles);
    $panels = Mockery::mock(PanelPluginSetup::class);
    $panels->shouldReceive('ensure')->once()->andReturn(true);
    $this->app->instance(PanelPluginSetup::class, $panels);
    $command = new class extends MinimalStarterInstallCommand
    {
        protected function restartInstallation(): int
        {
            return 7;
        }

        protected function publishOwnConfig(): void
        {
            throw new RuntimeException('Stale parent must not publish.');
        }
    };
    $command->setLaravel($this->app);
    $output = new BufferedOutput;
    expect($command->run(new ArrayInput([]), $output))->toBe(7);
    expect($output->fetch())->toContain('Restarting installation');
});
