<?php

namespace App\Filament\Resources;

use App\Enums\Account\AccountRole;
use App\Filament\Resources\AccountResource\Pages;
use App\Filament\Resources\AccountResource\RelationManagers;
use App\Models\Account;
use Filament\Actions;
use Filament\Forms\Components as Field;
use Filament\Resources\Resource;
use Filament\Schemas\Components as Layout;
use Filament\Schemas\Schema;
use Filament\Tables\Columns;
use Filament\Tables\Filters;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AccountResource extends Resource
{
    protected static ?string $model = Account::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-users';

    protected static \UnitEnum|string|null $navigationGroup = 'Khách hàng';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Tài khoản';

    protected static ?string $modelLabel = 'Tài khoản';

    protected static ?string $pluralModelLabel = 'Tài khoản';

    public static function form(Schema $schema): Schema
    {
        $readOnlyOnView = fn (string $operation): bool => $operation === 'view';

        return $schema->columns(1)->components([
            Layout\Section::make('Chi tiết tài khoản')
                ->description('Quản lý thông tin đăng nhập và quyền hạn')
                ->columns(2)
                ->components([
                    Field\TextInput::make('email')
                        ->label('Email')
                        ->email()
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(255)
                        ->disabled($readOnlyOnView),

                    Field\TextInput::make('password')
                        ->label('Mật khẩu')
                        ->password()
                        ->required(fn (string $operation): bool => $operation === 'create')
                        ->dehydrated(fn (?string $state) => filled($state))
                        ->hidden(fn (string $operation): bool => $operation === 'view'),

                    Field\Select::make('role')
                        ->label('Vai trò')
                        ->options(AccountRole::class)
                        ->default(AccountRole::Customer)
                        ->native(false)
                        ->required()
                        ->disabled($readOnlyOnView),

                    Field\TextInput::make('created_at')
                        ->label('Ngày tạo')
                        ->disabled()
                        ->hidden(fn (string $operation): bool => $operation === 'create')
                        ->formatStateUsing(fn (?Account $record): ?string => $record?->created_at?->format('d/m/Y H:i')),
                        
                    Field\Toggle::make('is_active')
                        ->label('Đang hoạt động')
                        ->inline(false)
                        ->default(true)
                        ->hidden(fn (string $operation): bool => $operation === 'create')
                        ->disabled($readOnlyOnView),
                ]),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('profile'))
            ->columns([
                Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable(),

                Columns\TextColumn::make('role')
                    ->label('Vai trò')
                    ->badge(),

                Columns\IconColumn::make('is_active')
                    ->label('Hoạt động')
                    ->boolean(),

                Columns\TextColumn::make('created_at')
                    ->label('Ngày tạo')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filters\SelectFilter::make('role')
                    ->label('Vai trò')
                    ->options(AccountRole::class)
                    ->native(false),
            ])
            ->recordActions([
                Actions\ViewAction::make()->label('Xem'),
            ])
            ->recordActionsColumnLabel('Thao tác')
            ->recordUrl(fn (Account $record): string => static::getUrl('view', ['record' => $record]));
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\OrdersRelationManager::class,
            RelationManagers\ReviewsRelationManager::class,
        ];
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAccounts::route('/'),
            'create' => Pages\CreateAccount::route('/create'),
            'view' => Pages\ViewAccount::route('/{record}'),
        ];
    }
}
