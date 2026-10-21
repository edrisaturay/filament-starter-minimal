<?php

namespace App\Filament\Resources\Users;

use Filament\Facades\Filament;
use Filament\Tables\Table;

class UserResource extends \TomatoPHP\FilamentUsers\Filament\Resources\Users\UserResource
{
    public static function table(Table $table): Table
    {
        $table = parent::table($table);
        $table->getAction('impersonate')?->authorize(fn (): bool => static::canImpersonateUsers());

        return $table;
    }

    public static function canImpersonateUsers(): bool
    {
        $user = Filament::auth()->user();

        return $user !== null && method_exists($user, 'hasRole')
            && $user->hasRole(config('filament-starter-minimal.superadmin.role', 'super_admin'));
    }

    public static function getPages(): array
    {
        return config('filament-users.simple')
            ? ['index' => Pages\ManageUsers::route('/')]
            : [
                'index' => Pages\ListUsers::route('/'),
                'create' => Pages\CreateUser::route('/create'),
                'edit' => Pages\EditUser::route('/{record}/edit'),
                'view' => Pages\ViewUser::route('/{record}'),
            ];
    }
}
