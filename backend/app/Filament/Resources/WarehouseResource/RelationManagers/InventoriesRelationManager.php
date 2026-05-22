<?php

namespace App\Filament\Resources\WarehouseResource\RelationManagers;

use App\Filament\Concerns\CreatesInventoryViaRestockService;
use App\Filament\Support\InventoryFilamentRules;
use App\Models\Inventory;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class InventoriesRelationManager extends RelationManager
{
    use CreatesInventoryViaRestockService;

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
                    ->required(),
                Forms\Components\TextInput::make('quantity')
                    ->label('Số lượng tồn')
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->required()
                    ->mutateStateForValidationUsing(fn (mixed $state): int => $state === '' || $state === null ? 0 : (int) $state)
                    ->rules(['integer', 'min:0'])
                    ->dehydrateStateUsing(fn (mixed $state): int => max(0, (int) ($state === '' || $state === null ? 0 : $state))),
                Forms\Components\TextInput::make('location_code')
                    ->label('Mã vị trí')
                    ->maxLength(50)
                    ->mutateStateForValidationUsing(fn (mixed $state): string => $state === null ? '' : trim((string) $state))
                    ->rules(['string', 'max:50'])
                    ->dehydrateStateUsing(fn (mixed $state): string => $state === null ? '' : trim((string) $state)),
                Forms\Components\DateTimePicker::make('last_restocked_at')
                    ->label('Nhập kho gần nhất')
                    ->seconds(false)
                    ->maxDate(fn (): \Illuminate\Support\Carbon => now()),
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
                    ->color(fn (Inventory $record): string => InventoryFilamentRules::availableStockBadgeColor($record)),
                Tables\Columns\TextColumn::make('sold_quantity')
                    ->label('Đã bán')
                    ->sortable(),
                Tables\Columns\TextColumn::make('location_code')
                    ->label('Mã vị trí'),
                Tables\Columns\TextColumn::make('last_restocked_at')
                    ->label('Nhập kho gần nhất')
                    ->dateTime('d/m/Y H:i')
                    ->toggleable(),
            ])
            ->headerActions([
                Actions\CreateAction::make()
                    ->using(function (array $data, RelationManager $livewire): Model {
                        $data['warehouse_id'] = $livewire->getOwnerRecord()->getKey();

                        return $livewire->createInventoryViaRestock($data);
                    }),
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
