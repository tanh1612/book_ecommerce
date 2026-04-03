<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AccountResource\Pages;
use App\Models\Account;
use Filament\Actions;
use Filament\Forms\Components as Field;
use Filament\Schemas\Components as Layout;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns;
use Filament\Tables\Filters;
use Filament\Tables\Table;

class AccountResource extends Resource
{
    protected static ?string $model = Account::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-users';

    protected static \UnitEnum|string|null $navigationGroup = 'Người dùng';
    protected static ?int $navigationSort = 1;
    protected static ?string $navigationLabel = 'Tài khoản';
    protected static ?string $modelLabel = 'Tài khoản';
    protected static ?string $pluralModelLabel = 'Tài khoản';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Layout\Section::make('Chi tiết tài khoản')
                ->description('Quản lý thông tin đăng nhập và quyền hạn')
                ->components([
                    Field\TextInput::make('email')
                        ->label('Email')
                        ->email()
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(255),

                    Field\TextInput::make('password')
                        ->label('Password')
                        ->password()
                        ->required(fn (string $operation): bool => $operation === 'create')
                        ->dehydrated(fn (?string $state) => filled($state)),

                    Field\Select::make('role')
                        ->label('Role')
                        ->options([
                            'admin' => 'Quản trị viên',
                            'customer' => 'Khách hàng',
                        ])
                        ->default('customer')
                        ->required(),

                    Field\Toggle::make('is_active')
                        ->label('Active')
                        ->inline(false)
                        ->default(true),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable(),

                Columns\TextColumn::make('role')
                    ->label('Role')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'admin' => 'danger',
                        'customer' => 'success',
                        default => 'primary',
                    }),

                Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                Columns\TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filters\SelectFilter::make('role')
                    ->label('Role')
                    ->options([
                        'admin' => 'Admin',
                        'customer' => 'Customer',
                    ]),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAccounts::route('/'),
            'create' => Pages\CreateAccount::route('/create'),
            'edit' => Pages\EditAccount::route('/{record}/edit'),
        ];
    }
}
