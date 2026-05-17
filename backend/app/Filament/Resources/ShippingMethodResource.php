<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ShippingMethodResource\Pages;
use App\Filament\Resources\ShippingMethodResource\RelationManagers;
use App\Models\ShippingMethod;
use Filament\Actions;
use Filament\Forms\Components as Field;
use Filament\Resources\Resource;
use Filament\Schemas\Components as Layout;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ShippingMethodResource extends Resource
{
    protected static ?string $model = ShippingMethod::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-paper-airplane';

    protected static \UnitEnum|string|null $navigationGroup = 'Kho hàng';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Phương thức vận chuyển';

    protected static ?string $modelLabel = 'Phương thức vận chuyển';

    protected static ?string $pluralModelLabel = 'Phương thức vận chuyển';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Layout\Section::make('Thông tin phương thức')
                ->columns(1)
                ->components([
                    Layout\Grid::make(12)
                        ->columnSpanFull()
                        ->components([
                            Field\TextInput::make('name')
                                ->label('Tên phương thức')
                                ->required()
                                ->maxLength(255)
                                ->columnSpan(['default' => 'full', 'lg' => 8]),
                            Field\Toggle::make('is_active')
                                ->label('Đang hoạt động')
                                ->inline(false)
                                ->default(true)
                                ->columnSpan(['default' => 'full', 'lg' => 4]),
                        ]),
                    Field\Textarea::make('description')
                        ->label('Mô tả')
                        ->maxLength(500)
                        ->rows(4)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Tên')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('description')->label('Mô tả')->limit(50),
                Tables\Columns\IconColumn::make('is_active')->label('Hoạt động')->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')->label('Trạng thái'),
            ])
            ->actions([Actions\EditAction::make(), Actions\DeleteAction::make()])
            ->bulkActions([Actions\BulkActionGroup::make([Actions\DeleteBulkAction::make()])]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\RatesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListShippingMethods::route('/'),
            'create' => Pages\CreateShippingMethod::route('/create'),
            'edit' => Pages\EditShippingMethod::route('/{record}/edit'),
        ];
    }
}
