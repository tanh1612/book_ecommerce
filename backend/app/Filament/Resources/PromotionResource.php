<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PromotionResource\Pages;
use App\Models\Promotion;
use Filament\Forms\Components as Field;
use Filament\Schemas\Components as Layout;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Actions;
use Filament\Tables;
use Filament\Tables\Table;
use App\Enums\Promotion\PromotionStatus;

class PromotionResource extends Resource
{
    protected static ?string $model = Promotion::class;
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-gift';
    protected static \UnitEnum|string|null $navigationGroup = 'Kinh doanh';
    protected static ?int $navigationSort = 2;
    protected static ?string $navigationLabel = 'Khuyến mãi';
    protected static ?string $modelLabel = 'Khuyến mãi';
    protected static ?string $pluralModelLabel = 'Khuyến mãi';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Layout\Section::make()->components([
                Field\TextInput::make('name')->label('Promotion Name')->required()->maxLength(255),
                Field\Select::make('type')->label('Type')->options(['flash_sale' => 'Flash Sale', 'discount' => 'Giảm giá', 'bundle' => 'Gói sản phẩm'])->required(),
                Layout\Grid::make(2)->components([
                    Field\DateTimePicker::make('start_at')->label('Start At')->required(),
                    Field\DateTimePicker::make('end_at')->label('End At')->required()->after('start_at'),
                ]),
                Field\Select::make('status')->label('Status')->options(PromotionStatus::class)->default(PromotionStatus::SCHEDULED)->required(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('type')->label('Type')->badge(),
                Tables\Columns\TextColumn::make('start_at')->label('Start At')->dateTime('d/m/Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('end_at')->label('End At')->dateTime('d/m/Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('status')->label('Status')->badge(),
            ])
            ->actions([Actions\EditAction::make(), Actions\DeleteAction::make()])
            ->bulkActions([Actions\BulkActionGroup::make([Actions\DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPromotions::route('/'),
            'create' => Pages\CreatePromotion::route('/create'),
            'edit' => Pages\EditPromotion::route('/{record}/edit'),
        ];
    }
}
