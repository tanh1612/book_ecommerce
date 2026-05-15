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
                Forms\Components\Select::make('discount_type')
                    ->label('Kiểu giảm')
                    ->options([
                        'percentage' => 'Phần trăm (%)',
                        'fixed' => 'Số tiền cố định (₫)',
                    ])
                    ->required(),
                Forms\Components\TextInput::make('discount_value')
                    ->label('Giá trị giảm')
                    ->numeric()
                    ->required(),
                Forms\Components\TextInput::make('stock_limit')
                    ->label('Giới hạn tồn bán')
                    ->numeric(),
                Forms\Components\TextInput::make('max_quantity_per_user')
                    ->label('Tối đa / khách')
                    ->numeric(),
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
                Tables\Columns\TextColumn::make('discount_type')
                    ->label('Kiểu')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'percentage' => '%',
                        'fixed' => '₫',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('discount_value')
                    ->label('Giá trị')
                    ->sortable(),
                Tables\Columns\TextColumn::make('stock_limit')
                    ->label('Giới hạn')
                    ->placeholder('∞'),
                Tables\Columns\TextColumn::make('sold_quantity')
                    ->label('Đã bán')
                    ->sortable(),
                Tables\Columns\TextColumn::make('max_quantity_per_user')
                    ->label('Tối đa / khách')
                    ->placeholder('∞'),
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
