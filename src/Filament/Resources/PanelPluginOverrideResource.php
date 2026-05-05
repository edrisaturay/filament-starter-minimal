<?php

namespace EdrisaTuray\FilamentStarterMinimal\Filament\Resources;

use EdrisaTuray\FilamentStarterMinimal\Contracts\PluginRegistryContract;
use EdrisaTuray\FilamentStarterMinimal\Filament\Resources\Concerns\AuthorizesPlatformAccess;
use EdrisaTuray\FilamentStarterMinimal\Models\PanelPluginOverride;
use EdrisaTuray\FilamentStarterMinimal\Models\PanelSnapshot;
use EdrisaTuray\FilamentStarterMinimal\Registry\PluginDefinition;
use EdrisaTuray\FilamentStarterMinimal\Support\PluginStateResolver;
use EdrisaTuray\FilamentStarterMinimal\Support\PluginSyncManager;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Cache\LockTimeoutException;

class PanelPluginOverrideResource extends Resource
{
    use AuthorizesPlatformAccess;

    protected static ?string $model = PanelPluginOverride::class;

    protected static ?string $navigationLabel = 'Plugin Management';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static string|\UnitEnum|null $navigationGroup = 'Platform';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Select::make('panel_id')
                    ->options(fn (): array => static::panelOptions())
                    ->required(),
                Select::make('plugin_key')
                    ->options(fn (): array => static::pluginOptions())
                    ->required()
                    ->live(),
                Toggle::make('enabled')
                    ->nullable()
                    ->helperText(fn ($record) => $record && $record->is_dangerous ? 'This plugin is marked as dangerous. It cannot be disabled.' : null)
                    ->disabled(fn ($record) => $record && $record->is_dangerous)
                    ->dehydrated(),
                Toggle::make('is_dangerous')
                    ->label('Dangerous (read-only)')
                    ->helperText('Derived from the plugin registry — cannot be edited from this form.')
                    ->disabled()
                    ->dehydrated(false),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('panel_id')->searchable()->sortable(),
                TextColumn::make('plugin_key')->searchable()->sortable(),
                ToggleColumn::make('enabled')
                    ->disabled(fn ($record) => $record && $record->is_dangerous),
                IconColumn::make('is_dangerous')
                    ->label('Dangerous')
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('panel_id')
                    ->options(fn (): array => static::panelOptions()),
                SelectFilter::make('plugin_key')
                    ->options(fn (): array => static::pluginOptions()),
                TernaryFilter::make('enabled'),
            ])
            ->headerActions([
                Action::make('sync_plugins')
                    ->label('Sync from Registry')
                    ->action(function (): void {
                        try {
                            PluginSyncManager::sync();
                            Notification::make()
                                ->title('Sync completed.')
                                ->success()
                                ->send();
                        } catch (LockTimeoutException $e) {
                            Notification::make()
                                ->title('Another sync is in progress.')
                                ->body('Try again in a moment.')
                                ->warning()
                                ->send();
                        } catch (\Throwable $e) {
                            report($e);
                            Notification::make()
                                ->title('Sync failed.')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->requiresConfirmation()
                    ->color('info'),
                Action::make('clear_cache')
                    ->label('Clear Plugin Cache')
                    ->action(function (): void {
                        foreach (\Filament\Facades\Filament::getPanels() as $panelId => $panel) {
                            PluginStateResolver::invalidate($panelId);
                        }
                        Notification::make()
                            ->title('Plugin cache cleared successfully')
                            ->success()
                            ->send();
                    })
                    ->requiresConfirmation()
                    ->color('warning'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => PanelPluginOverrideResource\Pages\ListPanelPluginOverrides::route('/'),
            'create' => PanelPluginOverrideResource\Pages\CreatePanelPluginOverride::route('/create'),
            'edit' => PanelPluginOverrideResource\Pages\EditPanelPluginOverride::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return static::isSuperAdmin();
    }

    public static function canEdit($record): bool
    {
        return static::isSuperAdmin();
    }

    public static function canCreate(): bool
    {
        return static::isSuperAdmin();
    }

    public static function canDelete($record): bool
    {
        return static::isSuperAdmin();
    }

    /**
     * Options array for `panel_id` selects — snapshots first, falling back to
     * currently-registered Filament panels when the snapshot table is empty.
     *
     * @return array<string, string>
     */
    private static function panelOptions(): array
    {
        $snapshots = PanelSnapshot::query()->pluck('panel_id', 'panel_id')->toArray();
        if ($snapshots !== []) {
            return $snapshots;
        }

        return collect(\Filament\Facades\Filament::getPanels())
            ->mapWithKeys(fn ($panel): array => [$panel->getId() => $panel->getId()])
            ->toArray();
    }

    /**
     * Options array for `plugin_key` selects — sourced directly from the
     * registry contract (no array-shape detour).
     *
     * @return array<string, string>
     */
    private static function pluginOptions(): array
    {
        return collect(app(PluginRegistryContract::class)->all())
            ->mapWithKeys(fn (PluginDefinition $def, string $key): array => [$key => $def->label])
            ->toArray();
    }
}
