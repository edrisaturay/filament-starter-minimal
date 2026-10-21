<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;

class ListUsers extends \TomatoPHP\FilamentUsers\Filament\Resources\Users\Pages\ListUsers
{
    protected static string $resource = UserResource::class;
}
