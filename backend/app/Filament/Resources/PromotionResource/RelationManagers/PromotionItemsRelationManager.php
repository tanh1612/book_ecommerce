<?php

namespace App\Filament\Resources\PromotionResource\RelationManagers;

use Filament\Actions;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class PromotionItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Sản phẩm trong chương trình';

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
                Forms\Components\TextInput::make('discount_value')
                    ->label('Phần trăm giảm')
                    ->numeric()
                    ->minValue(0.01)
                    ->maxValue(100)
                    ->suffix('%')
                    ->required(),
                Forms\Components\TextInput::make('stock_limit')
                    ->label('Giới hạn suất bán')
                    ->integer()
                    ->minValue(1),
                Forms\Components\TextInput::make('max_quantity_per_user')
                    ->label('Tối đa / khách')
                    ->integer()
                    ->minValue(1),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('book.name')
                    ->label('Sách')
                    ->limit(30)
                    ->searchable(),
                Tables\Columns\TextColumn::make('discount_value')
                    ->label('Giảm')
                    ->suffix('%')
                    ->sortable(),
                Tables\Columns\TextColumn::make('stock_limit')
                    ->label('Giới hạn')
                    ->placeholder('Không giới hạn'),
                Tables\Columns\TextColumn::make('sold_quantity')
                    ->label('Đã giữ/bán')
                    ->sortable(),
                Tables\Columns\TextColumn::make('max_quantity_per_user')
                    ->label('Tối đa / khách')
                    ->placeholder('Không giới hạn'),
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
