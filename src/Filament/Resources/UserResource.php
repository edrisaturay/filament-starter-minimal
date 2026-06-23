<?php

namespace EdrisaTuray\FilamentStarterMinimal\Filament\Resources;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;

/**
 * Default UserResource shipped with the minimal starter. Wires in the
 * stechstudio/filament-impersonate action automatically. Opt-out via
 * config('filament-starter-minimal.users.enabled') when the consuming
 * app already provides its own UserResource.
 *
 * Model class resolves from filament-starter-minimal.users.model, falling
 * back to auth.providers.users.model so most apps need no configuration.
 */
class UserResource extends Resource
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static string|\UnitEnum|null $navigationGroup = 'Platform';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModel(): string
    {
        /** @var class-string|null $configured */
        $configured = config('filament-starter-minimal.users.model');

        return $configured
            ?? config('auth.providers.users.model')
            ?? 'App\\Models\\User';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(2)
                    ->components([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        TextInput::make('password')
                            ->password()
                            ->revealable()
                            ->dehydrateStateUsing(fn ($state): ?string => filled($state) ? Hash::make($state) : null)
                            ->dehydrated(fn ($state): bool => filled($state))
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->maxLength(255),
                        DateTimePicker::make('email_verified_at')
                            ->label('Email verified at'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        $recordActions = [
            ViewAction::make(),
            EditAction::make(),
            DeleteAction::make(),
        ];

        // Class is guaranteed present (hard composer require) but keep the guard
        // so a consumer removing the package in a fork won't break the resource.
        $impersonate = '\\STS\\FilamentImpersonate\\Actions\\Impersonate';
        if (class_exists($impersonate)) {
            array_unshift($recordActions, $impersonate::make());
        }

        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('email')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                IconColumn::make('email_verified_at')
                    ->label('Verified')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->recordActions($recordActions)
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => UserResource\Pages\ListUsers::route('/'),
            'create' => UserResource\Pages\CreateUser::route('/create'),
            'edit' => UserResource\Pages\EditUser::route('/{record}/edit'),
            'view' => UserResource\Pages\ViewUser::route('/{record}'),
        ];
    }
}
