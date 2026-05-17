<?php

namespace App\Filament\Resources\ShippingMethodResource\RelationManagers;

use App\Models\ShippingRate;
use App\Support\Shipping\ShippingRateRegion;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class RatesRelationManager extends RelationManager
{
    protected static string $relationship = 'rates';

    protected static ?string $title = 'Biểu phí theo khu vực';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\Select::make('province_code')
                    ->label('Khu vực')
                    ->options(function (?Model $record): array {
                        $code = $record instanceof ShippingRate ? $record->getAttribute('province_code') : null;

                        return ShippingRateRegion::selectOptionsWith(is_string($code) ? $code : null);
                    })
                    ->helperText('Hà Nội hoặc TP.HCM sẽ áp dụng phí ship nội thành. Chọn Ngoại thành cho phí ship mặc định các tỉnh khác.')
                    ->formatStateUsing(fn ($state): string => ShippingRateRegion::normalizeToSelect($state))
                    ->dehydrateStateUsing(fn ($state): ?string => ShippingRateRegion::normalizeToDatabase($state))
                    ->required(),
                Forms\Components\TextInput::make('base_fee')
                    ->label('Phí cơ bản')
                    ->numeric()
                    ->prefix('₫')
                    ->minValue(0)
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('province_code')
                    ->label('Khu vực')
                    // Filament bỏ qua formatState khi state blank — province_code NULL phải lấy nhãn qua getStateUsing
                    ->getStateUsing(fn (ShippingRate $record): string => ShippingRateRegion::tableLabel($record->province_code))
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
