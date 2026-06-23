<?php

namespace EdrisaTuray\FilamentStarterMinimal\Filament\Resources\UserResource\Pages;

use EdrisaTuray\FilamentStarterMinimal\Filament\Resources\UserResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        $actions = [
            EditAction::make(),
        ];

        $impersonate = '\\STS\\FilamentImpersonate\\Actions\\Impersonate';
        if (class_exists($impersonate)) {
            array_unshift($actions, $impersonate::make()->record($this->getRecord()));
        }

        return $actions;
    }
}
