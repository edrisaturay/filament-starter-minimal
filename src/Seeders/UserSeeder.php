<?php

namespace EdrisaTuray\FilamentStarterMinimal\Seeders;

use Filament\Facades\Filament;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $model = config('filament-starter-minimal.users.model') ?: config('auth.providers.users.model');

        if (! is_string($model) || ! method_exists($model, 'assignRole')) {
            throw new RuntimeException('The configured user model must use Spatie\\Permission\\Traits\\HasRoles.');
        }

        $user = $model::firstOrCreate([
            'email' => config('filament-starter-minimal.seeders.admin.email', 'edrisa@edrisa.com'),
        ], [
            'name' => config('filament-starter-minimal.seeders.admin.name', 'Edrisa Turay'),
            'password' => Hash::make(config('filament-starter-minimal.seeders.admin.password', 'password')),
            'email_verified_at' => now(),
        ]);

        $roleModel = config('permission.models.role');
        $guards = collect(Filament::getPanels())
            ->filter(fn ($panel): bool => in_array($panel->getId(), config('filament-starter-minimal.managed_panels', []), true))
            ->map(fn ($panel): string => $panel->getAuthGuard())
            ->unique();

        if ($guards->isEmpty()) {
            $guards->push(config('auth.defaults.guard', 'web'));
        }

        foreach ($guards as $guard) {
            $user->assignRole($roleModel::findOrCreate(
                config('filament-starter-minimal.superadmin.role', 'super_admin'),
                $guard,
            ));
        }
    }
}
