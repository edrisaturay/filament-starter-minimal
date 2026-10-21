<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;

class CreateUser extends \TomatoPHP\FilamentUsers\Filament\Resources\Users\Pages\CreateUser
{
    protected static string $resource = UserResource::class;
}
