<?php

namespace EdrisaTuray\FilamentStarterMinimal\Filament\Users;

use EdrisaTuray\FilamentStarterMinimal\Filament\Actions\Impersonate;
use Filament\Actions\Action;

class UserActions extends \TomatoPHP\FilamentUsers\Filament\Resources\Users\Tables\UserActions
{
    public static function make(): array
    {
        $actions = array_values(array_filter(
            parent::make(),
            fn (Action $action): bool => $action->getName() !== 'impersonate',
        ));
        $actions[] = Impersonate::make();

        return $actions;
    }
}
