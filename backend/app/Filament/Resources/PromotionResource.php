<?php

namespace App\Filament\Resources;

use App\Enums\Promotion\PromotionStatus;
use App\Enums\Promotion\PromotionType;
use App\Filament\Resources\PromotionResource\Pages;
use App\Filament\Resources\PromotionResource\RelationManagers\PromotionItemsRelationManager;
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
                                ->options(PromotionType::class)
                                ->default(PromotionType::REGULAR_SALE)
                                ->required()
                                ->helperText('Hệ thống tự áp dụng ưu đãi có phần trăm giảm cao nhất cho khách.')
                                ->columnSpan(['default' => 'full', 'lg' => 6]),
                            Field\Select::make('status')
                                ->label('Trạng thái')
                                ->options(PromotionStatus::class)
                                ->disabled()
                                ->dehydrated(false)
                                ->hidden(fn (string $operation): bool => $operation === 'create')
                                ->columnSpan(['default' => 'full', 'lg' => 6]),
                        ]),
                    Layout\Grid::make(12)
                        ->columnSpanFull()
                        ->components([
                            Field\DateTimePicker::make('start_at')
                                ->label('Bắt đầu')
                                ->required()
                                ->seconds(false)
                                ->minDate(now())
                                ->rule('after:now')
                                ->validationMessages([
                                    'after' => 'Thời gian bắt đầu phải ở tương lai.',
                                ])
                                ->columnSpan(['default' => 'full', 'lg' => 6]),
                            Field\DateTimePicker::make('end_at')
                                ->label('Kết thúc')
                                ->required()
                                ->seconds(false)
                                ->after('start_at')
                                ->rule('after:start_at')
                                ->validationMessages([
                                    'after' => 'Thời gian kết thúc phải sau thời gian bắt đầu.',
                                ])
                                ->columnSpan(['default' => 'full', 'lg' => 6]),
                        ]),
                ]),
            Layout\Section::make('Sản phẩm áp dụng')
                ->description('Thêm từng sách vào chiến dịch và cấu hình phần trăm giảm, giới hạn suất bán, giới hạn mỗi khách.')
                ->components([
                    Field\Repeater::make('items')
                        ->label('Sản phẩm')
                        ->relationship()
                        ->schema([
                            Layout\Grid::make(12)
                                ->schema([
                                    Field\Select::make('book_id')
                                        ->label('Sách')
                                        ->relationship('book', 'name')
                                        ->searchable()
                                        ->preload()
                                        ->required()
                                        ->columnSpan(['default' => 'full', 'lg' => 5]),
                                    Field\TextInput::make('discount_value')
                                        ->label('Giảm')
                                        ->numeric()
                                        ->minValue(0.01)
                                        ->maxValue(100)
                                        ->suffix('%')
                                        ->required()
                                        ->columnSpan(['default' => 'full', 'lg' => 2]),
                                    Field\TextInput::make('stock_limit')
                                        ->label('Giới hạn suất')
                                        ->integer()
                                        ->minValue(1)
                                        ->helperText('Để trống nếu không giới hạn.')
                                        ->columnSpan(['default' => 'full', 'lg' => 2]),
                                    Field\TextInput::make('max_quantity_per_user')
                                        ->label('Tối đa / khách')
                                        ->integer()
                                        ->minValue(1)
                                        ->helperText('Để trống nếu không giới hạn.')
                                        ->columnSpan(['default' => 'full', 'lg' => 2]),
                                    Field\TextInput::make('sold_quantity')
                                        ->label('Đã giữ/bán')
                                        ->integer()
                                        ->default(0)
                                        ->disabled()
                                        ->dehydrated(false)
                                        ->columnSpan(['default' => 'full', 'lg' => 1]),
                                ]),
                        ])
                        ->defaultItems(1)
                        ->addActionLabel('Thêm sách')
                        ->reorderable(false)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Tên')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Loại')
                    ->badge()
                    ->formatStateUsing(fn (PromotionType|string|null $state): string => $state instanceof PromotionType
                        ? (string) $state->getLabel()
                        : (PromotionType::tryFrom((string) $state)?->getLabel() ?? (string) $state)),
                Tables\Columns\TextColumn::make('start_at')
                    ->label('Bắt đầu')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('end_at')
                    ->label('Kết thúc')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Trạng thái')
                    ->badge(),
            ])
            ->actions([
                Actions\EditAction::make()
                    ->visible(fn (Promotion $record): bool => $record->status === PromotionStatus::SCHEDULED),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([Actions\BulkActionGroup::make([Actions\DeleteBulkAction::make()])]);
    }

    public static function getRelations(): array
    {
        return [
            PromotionItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPromotions::route('/'),
            'create' => Pages\CreatePromotion::route('/create'),
            'edit' => Pages\EditPromotion::route('/{record}/edit'),
        ];
    }

    public static function canEdit($record): bool
    {
        return parent::canEdit($record)
            && $record instanceof Promotion
            && $record->status === PromotionStatus::SCHEDULED;
    }
}
