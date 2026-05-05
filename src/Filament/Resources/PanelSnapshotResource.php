<?php

namespace EdrisaTuray\FilamentStarterMinimal\Filament\Resources;

use EdrisaTuray\FilamentStarterMinimal\Filament\Resources\Concerns\AuthorizesPlatformAccess;
use EdrisaTuray\FilamentStarterMinimal\Models\PanelSnapshot;
use EdrisaTuray\FilamentStarterMinimal\Support\PanelSnapshotManager;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PanelSnapshotResource extends Resource
{
    use AuthorizesPlatformAccess;

    protected static ?string $model = PanelSnapshot::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-camera';

    protected static string|\UnitEnum|null $navigationGroup = 'Platform';

    protected static ?int $navigationSort = 2;

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Panel')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('panel_id'),
                        TextEntry::make('last_seen_at')->dateTime(),
                        TextEntry::make('meta.path')->label('Path'),
                        TextEntry::make('meta.tenancy')
                            ->label('Tenancy')
                            ->formatStateUsing(fn ($state): string => $state === true ? 'enabled' : ($state === false ? 'disabled' : '—')),
                    ]),
                Section::make('Metadata')
                    ->schema([
                        KeyValueEntry::make('meta')->hiddenLabel(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('panel_id')->searchable()->sortable(),
                TextColumn::make('meta.path')->label('Path'),
                IconColumn::make('meta.tenancy')->label('Tenancy')->boolean(),
                TextColumn::make('last_seen_at')->dateTime()->sortable(),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->headerActions([
                Action::make('refresh_snapshots')
                    ->action(function (): void {
                        try {
                            PanelSnapshotManager::snapshot();
                            Notification::make()
                                ->title('Snapshots refreshed.')
                                ->success()
                                ->send();
                        } catch (LockTimeoutException $e) {
                            Notification::make()
                                ->title('Another snapshot is in progress.')
                                ->body('Try again in a moment.')
                                ->warning()
                                ->send();
                        } catch (\Throwable $e) {
                            report($e);
                            Notification::make()
                                ->title('Snapshot refresh failed.')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->requiresConfirmation()
                    ->visible(fn (): bool => static::isSuperAdmin()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => PanelSnapshotResource\Pages\ListPanelSnapshots::route('/'),
            'view' => PanelSnapshotResource\Pages\ViewPanelSnapshot::route('/{record}'),
        ];
    }

    public static function canViewAny(): bool
    {
        return static::isSuperAdmin();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }
}
