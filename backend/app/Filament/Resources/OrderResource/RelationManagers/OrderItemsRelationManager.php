<?php

namespace App\Filament\Resources\OrderResource\RelationManagers;

use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class OrderItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Sản phẩm trong đơn';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\Select::make('book_id')
                    ->label('Sách')
                    ->relationship('book', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                Forms\Components\TextInput::make('quantity')
                    ->label('Số lượng')
                    ->numeric()
                    ->required()
                    ->default(1),
                Forms\Components\TextInput::make('price')
                    ->label('Đơn giá')
                    ->numeric()
                    ->prefix('₫')
                    ->required(),
                Forms\Components\TextInput::make('discount_amount')
                    ->label('Giảm giá')
                    ->numeric()
                    ->prefix('₫')
                    ->default(0),
                Forms\Components\TextInput::make('total_price')
                    ->label('Thành tiền')
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
                    ->label('Sách')
                    ->limit(40),
                Tables\Columns\TextColumn::make('quantity')
                    ->label('SL'),
                Tables\Columns\TextColumn::make('price')
                    ->label('Đơn giá')
                    ->money('VND'),
                Tables\Columns\TextColumn::make('discount_amount')
                    ->label('Giảm giá')
                    ->money('VND'),
                Tables\Columns\TextColumn::make('total_price')
                    ->label('Thành tiền')
                    ->money('VND'),
                Tables\Columns\IconColumn::make('is_reviewed')
                    ->label('Đã đánh giá')
                    ->boolean(),
            ])
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
