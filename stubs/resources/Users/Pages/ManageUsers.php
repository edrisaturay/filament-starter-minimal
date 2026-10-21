<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;

class ManageUsers extends \TomatoPHP\FilamentUsers\Filament\Resources\Users\Pages\ManageUsers
{
    protected static string $resource = UserResource::class;
}
