<?php

namespace EdrisaTuray\FilamentStarterMinimal\Filament\Resources;

use EdrisaTuray\FilamentStarterMinimal\Filament\Resources\Concerns\AuthorizesPlatformAccess;
use EdrisaTuray\FilamentStarterMinimal\Models\AuditLog;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AuditLogResource extends Resource
{
    use AuthorizesPlatformAccess;

    protected static ?string $model = AuditLog::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static string|\UnitEnum|null $navigationGroup = 'Platform';

    protected static ?int $navigationSort = 4;

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Event')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('created_at')->dateTime(),
                        TextEntry::make('action')
                            ->badge()
                            ->color(fn (string $state): string => self::actionColor($state)),
                        TextEntry::make('actor.name')->label('Actor')->placeholder('system'),
                    ]),
                Section::make('Subject')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('panel_id'),
                        TextEntry::make('plugin_key'),
                    ]),
                Grid::make(2)
                    ->schema([
                        Section::make('Before')
                            ->schema([
                                KeyValueEntry::make('before')->hiddenLabel(),
                            ]),
                        Section::make('After')
                            ->schema([
                                KeyValueEntry::make('after')->hiddenLabel(),
                            ]),
                    ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('actor:id,name');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->dateTime()->sortable(),
                TextColumn::make('actor.name')
                    ->label('Actor')
                    ->placeholder('system')
                    ->searchable(),
                TextColumn::make('action')
                    ->badge()
                    ->color(fn (string $state): string => self::actionColor($state))
                    ->searchable(),
                TextColumn::make('panel_id')->searchable(),
                TextColumn::make('plugin_key')->searchable(),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->filters([
                SelectFilter::make('action')
                    ->options([
                        'update_plugin_state' => 'Update plugin state',
                        'delete_plugin_override' => 'Delete plugin override',
                    ]),
                SelectFilter::make('panel_id')
                    ->options(fn (): array => AuditLog::query()
                        ->whereNotNull('panel_id')
                        ->select('panel_id')
                        ->distinct()
                        ->pluck('panel_id', 'panel_id')
                        ->all()),
                Filter::make('created_at')
                    ->schema([
                        DatePicker::make('from'),
                        DatePicker::make('until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, string $date): Builder => $q->whereDate('created_at', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $q, string $date): Builder => $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => AuditLogResource\Pages\ListAuditLogs::route('/'),
            'view' => AuditLogResource\Pages\ViewAuditLog::route('/{record}'),
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

    public static function canDeleteAny(): bool
    {
        return false;
    }

    private static function actionColor(string $action): string
    {
        return match ($action) {
            'update_plugin_state' => 'warning',
            'delete_plugin_override' => 'danger',
            default => 'info',
        };
    }
}
