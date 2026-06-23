<?php

namespace EdrisaTuray\FilamentStarterMinimal\Filament\Resources\UserResource\Pages;

use EdrisaTuray\FilamentStarterMinimal\Filament\Resources\UserResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        $actions = [
            ViewAction::make(),
            DeleteAction::make(),
        ];

        $impersonate = '\\STS\\FilamentImpersonate\\Actions\\Impersonate';
        if (class_exists($impersonate)) {
            array_unshift($actions, $impersonate::make()->record($this->getRecord()));
        }

        return $actions;
    }
}
