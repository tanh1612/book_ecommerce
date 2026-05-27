<?php

namespace App\Filament\Resources;

use App\Enums\Promotion\PromotionStatus;
use App\Filament\Resources\PromotionResource\Pages;
use App\Filament\Resources\PromotionResource\RelationManagers\PromotionItemsRelationManager;
use App\Models\Book;
use App\Models\Promotion;
use App\Services\Promotion\PromotionLifecycleService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;
use Filament\Forms\Components as Field;
use Filament\Resources\Resource;
use Filament\Schemas\Components as Layout;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class PromotionResource extends Resource
{
    protected static ?string $model = Promotion::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-bolt';

    protected static \UnitEnum|string|null $navigationGroup = 'Khuyến mãi';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Flash Sale';

    protected static ?string $modelLabel = 'Flash Sale';

    protected static ?string $pluralModelLabel = 'Flash Sale';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Layout\Section::make('Thông tin chiến dịch')
                ->description('Sau thời điểm bắt đầu, chiến dịch không thể chỉnh sửa.')
                ->columns(1)
                ->components([
                    Field\TextInput::make('name')
                        ->label('Tên chiến dịch')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),
                    Layout\Grid::make(12)
                        ->columnSpanFull()
                        ->components([
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
                                ->native(true)
                                ->seconds(false)
                                ->displayFormat('d/m/Y H:i')
                                ->minDate(now()->addMinute()->startOfMinute())
                                ->rule('after:now')
                                ->validationMessages([
                                    'after' => 'Thời gian bắt đầu phải ở tương lai.',
                                ])
                                ->columnSpan(['default' => 'full', 'lg' => 6]),
                            Field\DateTimePicker::make('end_at')
                                ->label('Kết thúc')
                                ->required()
                                ->native(true)
                                ->seconds(false)
                                ->displayFormat('d/m/Y H:i')
                                ->after('start_at')
                                ->rule('after:start_at')
                                ->validationMessages([
                                    'after' => 'Thời gian kết thúc phải sau thời gian bắt đầu.',
                                ])
                                ->columnSpan(['default' => 'full', 'lg' => 6]),
                        ]),
                ]),
            Layout\Section::make('Sản phẩm áp dụng')
                ->description(fn (string $operation): string => $operation === 'create'
                    ? 'Thêm ít nhất một sách trước khi lưu. Sau khi tạo, bạn vẫn có thể chỉnh sửa danh sách trước thời điểm bắt đầu.'
                    : 'Quản lý sách tại tab «Sản phẩm trong chương trình» bên dưới.')
                ->components([
                    Field\Repeater::make('items')
                        ->label('Danh sách sách')
                        ->visible(fn (string $operation): bool => $operation === 'create')
                        ->minItems(1)
                        ->defaultItems(1)
                        ->addActionLabel('Thêm sách')
                        ->columns(2)
                        ->schema([
                            Field\Select::make('book_id')
                                ->label('Sách')
                                ->searchable()
                                ->preload()
                                ->required()
                                ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                                ->options(fn (): array => Book::query()
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->all())
                                ->columnSpanFull(),
                            Field\TextInput::make('discount_value')
                                ->label('Phần trăm giảm')
                                ->integer()
                                ->minValue(1)
                                ->maxValue(100)
                                ->suffix('%')
                                ->required(),
                            Field\TextInput::make('stock_limit')
                                ->label('Giới hạn suất bán')
                                ->integer()
                                ->minValue(1),
                            Field\TextInput::make('max_quantity_per_user')
                                ->label('Tối đa / khách')
                                ->integer()
                                ->minValue(1),
                        ]),
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
                    ->visible(fn (Promotion $record): bool => static::canEdit($record)),
                Actions\Action::make('cancel')
                    ->label('Hủy chiến dịch')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Hủy chiến dịch')
                    ->modalDescription('Chiến dịch sẽ chuyển sang trạng thái đã hủy. Sản phẩm trong chiến dịch được giữ nguyên.')
                    ->visible(fn (Promotion $record): bool => app(PromotionLifecycleService::class)->canCancel($record))
                    ->action(function (Promotion $record, Actions\Action $action): void {
                        try {
                            app(PromotionLifecycleService::class)->cancel($record);
                            Notification::make()
                                ->title('Đã hủy chiến dịch')
                                ->success()
                                ->send();
                        } catch (ValidationException $exception) {
                            Notification::make()
                                ->title('Không thể hủy')
                                ->body(collect($exception->errors())->flatten()->first())
                                ->danger()
                                ->send();
                            $action->halt();
                        }
                    }),
                Actions\DeleteAction::make()
                    ->visible(fn (Promotion $record): bool => static::canDelete($record))
                    ->action(function (Promotion $record, Actions\DeleteAction $action): void {
                        try {
                            app(PromotionLifecycleService::class)->deleteScheduledPromotion($record);
                        } catch (ValidationException $exception) {
                            Notification::make()
                                ->title('Không thể xóa')
                                ->body(collect($exception->errors())->flatten()->first())
                                ->danger()
                                ->send();
                            $action->halt();
                        }
                    }),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make()
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records, Actions\DeleteBulkAction $action): void {
                            $promotions = $records
                                ->filter(fn ($record): bool => $record instanceof Promotion)
                                ->values()
                                ->all();

                            try {
                                app(PromotionLifecycleService::class)->deleteScheduledPromotions($promotions);
                            } catch (ValidationException $exception) {
                                Notification::make()
                                    ->title('Không thể xóa')
                                    ->body(collect($exception->errors())->flatten()->first())
                                    ->danger()
                                    ->send();
                                $action->halt();
                            }
                        }),
                ]),
            ]);
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
            && app(PromotionLifecycleService::class)->canEdit($record);
    }

    public static function canDelete($record): bool
    {
        return parent::canDelete($record)
            && $record instanceof Promotion
            && app(PromotionLifecycleService::class)->canDelete($record);
    }
}
