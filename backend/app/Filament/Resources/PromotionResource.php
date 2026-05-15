<?php

namespace App\Filament\Resources;

use App\Enums\Promotion\PromotionStatus;
use App\Filament\Resources\PromotionResource\Pages;
use App\Models\Promotion;
use Filament\Actions;
use Filament\Forms\Components as Field;
use Filament\Resources\Resource;
use Filament\Schemas\Components as Layout;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class PromotionResource extends Resource
{
    protected static ?string $model = Promotion::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-gift';

    protected static \UnitEnum|string|null $navigationGroup = 'Khuyến mãi';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Khuyến mãi';

    protected static ?string $modelLabel = 'Khuyến mãi';

    protected static ?string $pluralModelLabel = 'Khuyến mãi';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Layout\Section::make('Thông tin chương trình')
                ->columns(1)
                ->components([
                    Field\TextInput::make('name')
                        ->label('Tên chương trình')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),
                    Layout\Grid::make(12)
                        ->columnSpanFull()
                        ->components([
                            Field\Select::make('type')
                                ->label('Loại')
                                ->options([
                                    'flash_sale' => 'Flash sale',
                                    'discount' => 'Giảm giá',
                                    'bundle' => 'Gói combo',
                                ])
                                ->required()
                                ->columnSpan(['default' => 'full', 'lg' => 6]),
                            Field\Select::make('status')
                                ->label('Trạng thái')
                                ->options(PromotionStatus::class)
                                ->default(PromotionStatus::SCHEDULED)
                                ->required()
                                ->columnSpan(['default' => 'full', 'lg' => 6]),
                        ]),
                    Layout\Grid::make(12)
                        ->columnSpanFull()
                        ->components([
                            Field\DateTimePicker::make('start_at')
                                ->label('Bắt đầu')
                                ->required()
                                ->columnSpan(['default' => 'full', 'lg' => 6]),
                            Field\DateTimePicker::make('end_at')
                                ->label('Kết thúc')
                                ->required()
                                ->after('start_at')
                                ->columnSpan(['default' => 'full', 'lg' => 6]),
                        ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Tên')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('type')->label('Loại')->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'flash_sale' => 'Flash sale',
                        'discount' => 'Giảm giá',
                        'bundle' => 'Gói combo',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('start_at')->label('Bắt đầu')->dateTime('d/m/Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('end_at')->label('Kết thúc')->dateTime('d/m/Y H:i')->sortable(),
                Tables\Columns\TextColumn::make('status')->label('Trạng thái')->badge(),
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
