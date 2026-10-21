<?php

namespace EdrisaTuray\FilamentStarterMinimal\Seeders;

use Filament\Facades\Filament;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;

class ShieldSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $roleModel = config('permission.models.role');
        $permissionModel = config('permission.models.permission');

        foreach (Filament::getPanels() as $panelId => $panel) {
            if (! in_array($panelId, config('filament-starter-minimal.managed_panels', []), true)) {
                continue;
            }

            $exit = Artisan::call('shield:generate', [
                '--all' => true,
                '--ignore-existing-policies' => true,
                '--panel' => $panelId,
                '--option' => 'policies_and_permissions',
                '--no-interaction' => true,
            ]);

            if ($exit !== 0) {
                throw new RuntimeException("Shield generation failed for panel [{$panelId}]: ".Artisan::output());
            }

            $role = $roleModel::findOrCreate(
                config('filament-starter-minimal.superadmin.role', 'super_admin'),
                $panel->getAuthGuard(),
            );
            $role->givePermissionTo($permissionModel::where('guard_name', $panel->getAuthGuard())->get());
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
