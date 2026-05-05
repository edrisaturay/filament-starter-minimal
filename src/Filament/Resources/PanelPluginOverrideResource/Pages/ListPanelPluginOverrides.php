<?php

namespace EdrisaTuray\FilamentStarterMinimal\Filament\Resources\PanelPluginOverrideResource\Pages;

use EdrisaTuray\FilamentStarterMinimal\Filament\Resources\PanelPluginOverrideResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPanelPluginOverrides extends ListRecords
{
    protected static string $resource = PanelPluginOverrideResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
