<?php

namespace App\Filament\Resources\BookResource\RelationManagers;

use App\Filament\Support\InventoryFilamentLabels;
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
                Forms\Components\Select::make('warehouse_id')
                    ->label(InventoryFilamentLabels::attribute('warehouse_id'))
                    ->relationship('warehouse', 'name', fn (Builder $query) => $query->where('is_active', true))
                    ->searchable()
                    ->preload()
                    ->required()
                    ->rule(fn (Get $get, RelationManager $livewire, ?Inventory $record) => InventoryFilamentRules::uniqueWarehouseForBookRelation($get, $livewire, $record)),
                Forms\Components\TextInput::make('quantity')
                    ->label(InventoryFilamentLabels::attribute('quantity'))
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->required()
                    ->rules(['integer']),
                Forms\Components\TextInput::make('sold_quantity')
                    ->label(InventoryFilamentLabels::attribute('sold_quantity'))
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->required()
                    ->rules(['integer']),
                Forms\Components\TextInput::make('reserved_quantity')
                    ->label(InventoryFilamentLabels::attribute('reserved_quantity'))
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->required()
                    ->rules(['integer'])
                    ->rule(InventoryFilamentRules::reservedQuantityLteOnHand()),
                Forms\Components\TextInput::make('location_code')
                    ->label(InventoryFilamentLabels::attribute('location_code'))
                    ->required()
                    ->maxLength(50),
                Forms\Components\DateTimePicker::make('last_restocked_at')
                    ->label(InventoryFilamentLabels::attribute('last_restocked_at'))
                    ->seconds(false),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('warehouse.name')
                    ->label(InventoryFilamentLabels::attribute('warehouse'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('quantity')
                    ->label(InventoryFilamentLabels::attribute('quantity'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('reserved_quantity')
                    ->label(InventoryFilamentLabels::attribute('reserved_quantity'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('available_stock')
                    ->label(InventoryFilamentLabels::attribute('available_stock'))
                    ->color(fn (Inventory $record): string => match (true) {
                        $record->available_stock <= 0 => 'danger',
                        $record->available_stock <= 5 => 'warning',
                        default => 'success',
                    }),
                Tables\Columns\TextColumn::make('sold_quantity')
                    ->label(InventoryFilamentLabels::attribute('sold_quantity'))
                    ->sortable(),
                Tables\Columns\TextColumn::make('location_code')
                    ->label(InventoryFilamentLabels::attribute('location_code')),
                Tables\Columns\TextColumn::make('last_restocked_at')
                    ->label(InventoryFilamentLabels::attribute('last_restocked_at'))
                    ->dateTime('d/m/Y H:i'),
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
