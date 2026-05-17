<?php

namespace App\Filament\Resources\WarehouseResource\RelationManagers;

use App\Filament\Support\InventoryFilamentRules;
use App\Models\Inventory;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class InventoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'inventories';

    protected static ?string $title = 'Tồn kho';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\Select::make('book_id')
                    ->label('Sách')
                    ->relationship('book', 'name', fn (Builder $query) => $query->where('is_active', true))
                    ->searchable()
                    ->preload()
                    ->required()
                    ->rule(fn (Get $get, ?Inventory $record) => InventoryFilamentRules::uniqueBookIdForInventory($record)),
                Forms\Components\TextInput::make('quantity')
                    ->label('Số lượng tồn')
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->mutateStateForValidationUsing(fn (mixed $state): int => $state === '' || $state === null ? 0 : (int) $state)
                    ->rules(['integer', 'min:0'])
                    ->dehydrateStateUsing(fn (mixed $state): int => max(0, (int) ($state === '' || $state === null ? 0 : $state))),
                Forms\Components\TextInput::make('sold_quantity')
                    ->label('Đã bán')
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->mutateStateForValidationUsing(fn (mixed $state): int => $state === '' || $state === null ? 0 : (int) $state)
                    ->rules(['integer', 'min:0'])
                    ->dehydrateStateUsing(fn (mixed $state): int => max(0, (int) ($state === '' || $state === null ? 0 : $state))),
                Forms\Components\TextInput::make('reserved_quantity')
                    ->label('Đang giữ')
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->mutateStateForValidationUsing(fn (mixed $state): int => $state === '' || $state === null ? 0 : (int) $state)
                    ->rules(['integer', 'min:0'])
                    ->dehydrateStateUsing(fn (mixed $state): int => max(0, (int) ($state === '' || $state === null ? 0 : $state)))
                    ->rule(InventoryFilamentRules::reservedQuantityLteOnHand()),
                Forms\Components\TextInput::make('location_code')
                    ->label('Mã vị trí')
                    ->maxLength(50)
                    ->mutateStateForValidationUsing(fn (mixed $state): string => $state === null ? '' : trim((string) $state))
                    ->rules(['string', 'max:50'])
                    ->dehydrateStateUsing(fn (mixed $state): string => $state === null ? '' : trim((string) $state)),
                Forms\Components\DateTimePicker::make('last_restocked_at')
                    ->label('Nhập kho gần nhất')
                    ->seconds(false),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('book.name')
                    ->label('Sách')
                    ->searchable()
                    ->limit(30),
                Tables\Columns\TextColumn::make('quantity')
                    ->label('Số lượng tồn')
                    ->sortable(),
                Tables\Columns\TextColumn::make('reserved_quantity')
                    ->label('Đang giữ')
                    ->sortable(),
                Tables\Columns\TextColumn::make('available_stock')
                    ->label('Có thể bán')
                    ->color(fn (Inventory $record): string => match (true) {
                        $record->available_stock <= 0 => 'danger',
                        $record->available_stock <= 5 => 'warning',
                        default => 'success',
                    }),
                Tables\Columns\TextColumn::make('sold_quantity')
                    ->label('Đã bán')
                    ->sortable(),
                Tables\Columns\TextColumn::make('location_code')
                    ->label('Mã vị trí'),
            ])
            ->headerActions([
                Actions\CreateAction::make(),
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
}
