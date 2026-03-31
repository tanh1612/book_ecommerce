<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InventoryResource\Pages;
use App\Models\Inventory;
use Filament\Forms\Components as Field;
use Filament\Schemas\Components as Layout;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Actions;
use Filament\Tables;
use Filament\Tables\Table;

class InventoryResource extends Resource
{
    protected static ?string $model = Inventory::class;
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-cube';
    protected static \UnitEnum|string|null $navigationGroup = 'Inventory';
    protected static ?int $navigationSort = 2;
    protected static ?string $navigationLabel = 'Inventory';
    protected static ?string $modelLabel = 'Inventory';
    protected static ?string $pluralModelLabel = 'Inventory';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Layout\Section::make()->components([
                Field\Select::make('book_id')
                    ->label('Book')
                    ->relationship('book', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Field\Select::make('warehouse_id')
                    ->label('Warehouse')
                    ->relationship('warehouse', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Layout\Grid::make(3)->components([
                    Field\TextInput::make('quantity')->label('Quantity')->numeric()->required()->default(0),
                    Field\TextInput::make('sold_quantity')->label('Sold Quantity')->numeric()->default(0),
                    Field\TextInput::make('reserved_quantity')->label('Reserved Quantity')->numeric()->default(0),
                ]),
                Field\TextInput::make('location_code')->label('Location Code'),
                Field\DateTimePicker::make('last_restocked_at')->label('Last Restocked At'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('book.name')->label('Book')->searchable()->sortable()->limit(40),
                Tables\Columns\TextColumn::make('warehouse.name')->label('Warehouse')->sortable(),
                Tables\Columns\TextColumn::make('quantity')->label('Stock Level')->sortable()->color(fn (int $state): string => $state <= 5 ? 'danger' : 'success'),
                Tables\Columns\TextColumn::make('sold_quantity')->label('Sold')->sortable(),
                Tables\Columns\TextColumn::make('location_code')->label('Location')->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('warehouse_id')->label('Warehouse')->relationship('warehouse', 'name')->searchable()->preload(),
            ])
            ->actions([Actions\EditAction::make(), Actions\DeleteAction::make()])
            ->bulkActions([Actions\BulkActionGroup::make([Actions\DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInventories::route('/'),
            'create' => Pages\CreateInventory::route('/create'),
            'edit' => Pages\EditInventory::route('/{record}/edit'),
        ];
    }
}
