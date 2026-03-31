<?php

namespace App\Filament\Resources\PromotionResource\RelationManagers;

use Filament\Forms;
use Filament\Schemas\Schema;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Actions;
use Filament\Tables;
use Filament\Tables\Table;

class PromotionItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Promotional Items';

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
                Forms\Components\Select::make('discount_type')
                    ->label('Discount Type')
                    ->options([
                        'percentage' => 'Percentage (%)',
                        'fixed' => 'Fixed Amount (₫)',
                    ])
                    ->required(),
                Forms\Components\TextInput::make('discount_value')
                    ->label('Discount Value')
                    ->numeric()
                    ->required(),
                Forms\Components\TextInput::make('stock_limit')
                    ->label('Stock Limit')
                    ->numeric(),
                Forms\Components\TextInput::make('max_quantity_per_user')
                    ->label('Limit Per User')
                    ->numeric(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('book.name')
                    ->label('Book')
                    ->limit(30)
                    ->searchable(),
                Tables\Columns\TextColumn::make('discount_type')
                    ->label('Type')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'percentage' => '%',
                        'fixed' => '₫',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('discount_value')
                    ->label('Value')
                    ->sortable(),
                Tables\Columns\TextColumn::make('stock_limit')
                    ->label('Limit')
                    ->placeholder('∞'),
                Tables\Columns\TextColumn::make('sold_quantity')
                    ->label('Sold')
                    ->sortable(),
                Tables\Columns\TextColumn::make('max_quantity_per_user')
                    ->label('Max/User')
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
