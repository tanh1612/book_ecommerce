<?php

namespace App\Filament\Resources\OrderResource\RelationManagers;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class OrderItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Ordered Items';

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
                    ->default(1),
                Forms\Components\TextInput::make('price')
                    ->label('Unit Price')
                    ->numeric()
                    ->prefix('₫')
                    ->required(),
                Forms\Components\TextInput::make('discount_amount')
                    ->label('Discount')
                    ->numeric()
                    ->prefix('₫')
                    ->default(0),
                Forms\Components\TextInput::make('total_price')
                    ->label('Subtotal')
                    ->numeric()
                    ->prefix('₫')
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('book.name')
                    ->label('Book')
                    ->limit(40),
                Tables\Columns\TextColumn::make('quantity')
                    ->label('Qty'),
                Tables\Columns\TextColumn::make('price')
                    ->label('Unit Price')
                    ->money('VND'),
                Tables\Columns\TextColumn::make('discount_amount')
                    ->label('Discount')
                    ->money('VND'),
                Tables\Columns\TextColumn::make('total_price')
                    ->label('Subtotal')
                    ->money('VND'),
                Tables\Columns\IconColumn::make('is_reviewed')
                    ->label('Is Reviewed')
                    ->boolean(),
            ])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
