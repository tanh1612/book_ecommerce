<?php

namespace App\Filament\Resources\WarehouseResource\RelationManagers;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Actions;
use Filament\Tables;
use Filament\Tables\Table;

class InventoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'inventories';

    protected static ?string $title = 'Warehouse Stock';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\Select::make('book_id')
                    ->label('Book')
                    ->relationship('book', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Forms\Components\TextInput::make('quantity')
                    ->label('Quantity')
                    ->numeric()
                    ->required()
                    ->default(0),
                Forms\Components\TextInput::make('sold_quantity')
                    ->label('Sold')
                    ->numeric()
                    ->default(0),
                Forms\Components\TextInput::make('reserved_quantity')
                    ->label('Reserved')
                    ->numeric()
                    ->default(0),
                Forms\Components\TextInput::make('location_code')
                    ->label('Location Code'),
                Forms\Components\DateTimePicker::make('last_restocked_at')
                    ->label('Last Restocked At'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('book.name')
                    ->label('Book')
                    ->searchable()
                    ->limit(30),
                Tables\Columns\TextColumn::make('quantity')
                    ->label('Stock Level')
                    ->sortable(),
                Tables\Columns\TextColumn::make('sold_quantity')
                    ->label('Sold')
                    ->sortable(),
                Tables\Columns\TextColumn::make('reserved_quantity')
                    ->label('Reserved')
                    ->sortable(),
                Tables\Columns\TextColumn::make('location_code')
                    ->label('Location Code'),
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
