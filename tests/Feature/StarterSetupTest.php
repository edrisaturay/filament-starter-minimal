<?php

use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\UserResource;
use EdrisaTuray\FilamentStarterMinimal\Commands\MinimalStarterInstallCommand;
use EdrisaTuray\FilamentStarterMinimal\FilamentStarterMinimalServiceProvider;
use EdrisaTuray\FilamentStarterMinimal\Registry\DefaultPluginCatalog;
use EdrisaTuray\FilamentStarterMinimal\Seeders\ShieldSeeder;
use EdrisaTuray\FilamentStarterMinimal\Seeders\UserSeeder;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\PanelRegistry;
use Filament\Support\Facades\FilamentView;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionServiceProvider;
use Spatie\Permission\Traits\HasRoles;
use STS\FilamentImpersonate\Actions\Impersonate;
use STS\FilamentImpersonate\Facades\Impersonation;
use STS\FilamentImpersonate\FilamentImpersonateServiceProvider;
use TomatoPHP\FilamentUsers\FilamentUsersPlugin;
use TomatoPHP\FilamentUsers\FilamentUsersServiceProvider;

trait UsesProviderMigrationsOnly
{
    protected function loadMigrationsFrom(array|string $paths): void
    {
        // RefreshDatabase already runs the migrations registered by the provider.
    }
}

uses(UsesProviderMigrationsOnly::class);

class StarterSeedUser extends User
{
    use HasRoles;

    protected $table = 'users';

    protected $guarded = [];

    protected $guard_name = 'web';
}

beforeEach(function (): void {
    $this->app->register(PermissionServiceProvider::class);
    config(['auth.providers.users.model' => StarterSeedUser::class]);
    if (! Schema::hasTable('users')) {
        Schema::create('users', function ($table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamps();
        });
    }
    $migration = require __DIR__.'/../../vendor/spatie/laravel-permission/database/migrations/create_permission_tables.php.stub';
    $migration->up();
    app(PanelRegistry::class)->register(Panel::make()->id('admin')->path('admin')->authGuard('web'));
    config(['filament-starter-minimal.managed_panels' => ['admin']]);
});

it('creates the requested verified super admin and preserves an existing password', function (): void {
    $this->seed(UserSeeder::class);
    $user = StarterSeedUser::where('email', 'edrisa@edrisa.com')->sole();
    expect(Hash::check('password', $user->password))->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->hasRole('super_admin'))->toBeTrue();
    $user->update(['password' => Hash::make('changed-password')]);
    $this->seed(UserSeeder::class);
    expect(StarterSeedUser::count())->toBe(1)
        ->and(Hash::check('changed-password', $user->fresh()->password))->toBeTrue();
});

it('generates Shield permissions before granting them to the configured role', function (): void {
    config(['filament-starter-minimal.superadmin.role' => 'owner']);
    Permission::create(['name' => 'ViewAny:User', 'guard_name' => 'web']);
    Artisan::command('shield:generate {--all} {--panel=} {--option=} {--ignore-existing-policies}', function (): int {
        expect($this->option('panel'))->toBe('admin');

        return 0;
    });
    app(ShieldSeeder::class)->run();
    expect(Role::findByName('owner', 'web')->hasPermissionTo('ViewAny:User'))->toBeTrue();
});

it('fails when Shield permission generation fails', function (): void {
    Artisan::command('shield:generate {--all} {--panel=} {--option=} {--ignore-existing-policies}', function (): int {
        $this->error('generation failed');

        return 1;
    });
    expect(fn () => app(ShieldSeeder::class)->run())->toThrow(RuntimeException::class, 'generation failed');
});

it('publishes both seeders without running them or overwriting existing files', function (): void {
    $path = sys_get_temp_dir().'/minimal-starter-'.bin2hex(random_bytes(8));
    $this->app->useDatabasePath($path);
    $this->app->register(FilamentStarterMinimalServiceProvider::class, true);
    try {
        $this->artisan('vendor:publish', ['--tag' => 'filament-starter-minimal-seeders'])->assertSuccessful();
        expect(File::exists($path.'/seeders/ShieldSeeder.php'))->toBeTrue()
            ->and(File::exists($path.'/seeders/UserSeeder.php'))->toBeTrue()
            ->and(StarterSeedUser::count())->toBe(0);
        File::put($path.'/seeders/UserSeeder.php', '<?php // custom seeder');
        $this->artisan('vendor:publish', ['--tag' => 'filament-starter-minimal-seeders'])->assertSuccessful();
        expect(File::get($path.'/seeders/UserSeeder.php'))->toBe('<?php // custom seeder');
    } finally {
        File::deleteDirectory($path);
    }
});

it('runs the published Shield seeder before the published user seeder', function (): void {
    $command = new class extends MinimalStarterInstallCommand
    {
        public array $calls = [];

        public function call($command, array $arguments = []): int
        {
            $this->calls[] = [$command, $arguments];

            return 0;
        }

        public function seed(): void
        {
            $this->setupShield();
        }
    };
    require_once __DIR__.'/../../stubs/seeders/ShieldSeeder.php';
    require_once __DIR__.'/../../stubs/seeders/UserSeeder.php';
    $command->seed();
    expect(array_column(array_column($command->calls, 1), '--class'))->toBe([
        'Database\\Seeders\\ShieldSeeder', 'Database\\Seeders\\UserSeeder',
    ]);
});

it('registers the published TomatoPHP resource without the vendor duplicate', function (): void {
    $this->app->register(FilamentUsersServiceProvider::class);
    require_once __DIR__.'/../../stubs/resources/Users/UserResource.php';
    foreach (glob(__DIR__.'/../../stubs/resources/Users/Pages/*.php') as $file) {
        require_once $file;
    }
    $definition = collect(DefaultPluginCatalog::definitions())->firstWhere('key', 'filament-users');
    $panel = Panel::make()->id('admin')->path('admin');
    ($definition->installer)($panel, $definition->defaultOptions);
    expect($panel->getResources())->toContain(UserResource::class)
        ->not->toContain(TomatoPHP\FilamentUsers\Filament\Resources\Users\UserResource::class)
        ->and(ListUsers::getResource())->toBe(UserResource::class)
        ->and(config('filament-users.impersonate.enabled'))->toBeFalse();
});

it('uses STS impersonation and restores the admin through the leave route', function (): void {
    config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
    $this->app->register(FilamentImpersonateServiceProvider::class);
    $this->app->register(FilamentUsersServiceProvider::class);
    $this->app['router']->getRoutes()->refreshNameLookups();
    $panel = Filament::getPanel('admin');
    Filament::setCurrentPanel($panel);
    config(['filament-users.shield' => false]);
    FilamentUsersPlugin::make()->boot($panel);
    $actions = config('filament-users.resource.table.actions')::make();
    $action = collect($actions)->first(fn ($action): bool => $action->getName() === 'impersonate');
    expect($action)->toBeInstanceOf(Impersonate::class)
        ->and(collect($actions)->filter(fn ($action): bool => $action->getName() === 'impersonate'))->toHaveCount(1);
    $this->seed(UserSeeder::class);
    $admin = StarterSeedUser::where('email', 'edrisa@edrisa.com')->sole();
    $ordinary = StarterSeedUser::create(['name' => 'User', 'email' => 'user@example.com', 'password' => Hash::make('test')]);
    $this->actingAs($ordinary);
    expect($action->impersonate($admin))->toBeFalse();
    $this->actingAs($admin);
    $action->redirectTo('/admin')->backTo('/admin/users');
    expect($action->impersonate($admin))->toBeFalse();
    expect($action->impersonate($ordinary))->toBeInstanceOf(RedirectResponse::class);
    expect(Impersonation::isImpersonating())->toBeTrue()
        ->and(auth('web')->id())->toBe($ordinary->id);
    $banner = FilamentView::renderHook('panels::body.start')->toHtml();
    expect($banner)->toContain(route('filament-impersonate.leave'))
        ->not->toContain('filament-users/impersonate');
    $this->get(route('filament-impersonate.leave'))->assertRedirect('/admin/users');
    expect(auth('web')->id())->toBe($admin->id)
        ->and(Impersonation::isImpersonating())->toBeFalse();
});

it('stops installation seeding when the Shield seeder fails', function (): void {
    $command = new class extends MinimalStarterInstallCommand
    {
        public array $calls = [];

        public function call($command, array $arguments = []): int
        {
            $this->calls[] = $arguments['--class'];

            return 1;
        }

        public function seed(): void
        {
            $this->setupShield();
        }
    };
    require_once __DIR__.'/../../stubs/seeders/ShieldSeeder.php';
    expect(fn () => $command->seed())->toThrow(RuntimeException::class, 'ShieldSeeder failed');
    expect($command->calls)->toBe(['Database\\Seeders\\ShieldSeeder']);
});
