<?php

namespace App\Filament\Resources\ShippingMethodResource\RelationManagers;

use Filament\Actions;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class RatesRelationManager extends RelationManager
{
    protected static string $relationship = 'rates';

    protected static ?string $title = 'Biểu phí theo tỉnh';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\TextInput::make('province_code')
                    ->label('Mã tỉnh / thành')
                    ->required()
                    ->maxLength(10),
                Forms\Components\TextInput::make('base_fee')
                    ->label('Phí cơ bản')
                    ->numeric()
                    ->prefix('₫')
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('province_code')
                    ->label('Mã tỉnh')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('base_fee')
                    ->label('Phí cơ bản')
                    ->money('VND')
                    ->sortable(),
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
