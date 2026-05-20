<?php

namespace App\Filament\Resources;

use App\Enums\Order\OrderStatus;
use App\Enums\Order\PaymentMethod;
use App\Enums\Order\PaymentStatus;
use App\Filament\Resources\OrderResource\Pages;
use App\Filament\Resources\OrderResource\RelationManagers;
use App\Models\Order;
use App\Services\Order\OrderInventoryService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Forms\Components as Field;
use Filament\Resources\Resource;
use Filament\Schemas\Components as Layout;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-shopping-bag';

    protected static \UnitEnum|string|null $navigationGroup = 'Đơn hàng';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Đơn hàng';

    protected static ?string $modelLabel = 'Đơn hàng';

    protected static ?string $pluralModelLabel = 'Đơn hàng';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Layout\Section::make('Thông tin đơn hàng')->components([
                Field\Select::make('account_id')->label('Khách hàng')->relationship('account', 'email')->searchable()->preload()->required(),
                Field\Select::make('current_status')->label('Trạng thái đơn')->options(OrderStatus::class)->required(),
                Field\Select::make('payment_method')->label('Phương thức thanh toán')->options(PaymentMethod::class),
                Field\Select::make('payment_status')->label('Trạng thái thanh toán')->options(PaymentStatus::class),
            ])->columns(2),
            Layout\Section::make('Giao hàng & tổng tiền')->components([
                Field\TextInput::make('shipping_name')->label('Tên người nhận')->maxLength(255),
                Field\TextInput::make('shipping_phone')->label('Số điện thoại')->maxLength(20),
                Field\TextInput::make('final_amount')->label('Tổng thanh toán')->numeric()->prefix('₫'),
            ])->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('Mã đơn')->prefix('#')->sortable(),
                Tables\Columns\TextColumn::make('account.email')->label('Khách hàng')->searchable()->limit(25),
                Tables\Columns\TextColumn::make('final_amount')->label('Tổng tiền')->money('VND')->sortable(),
                Tables\Columns\TextColumn::make('current_status')->label('Trạng thái đơn')->badge(),
                Tables\Columns\TextColumn::make('payment_status')->label('Thanh toán')->badge(),
                Tables\Columns\TextColumn::make('created_at')->label('Ngày tạo')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('current_status')->label('Trạng thái đơn')->options(OrderStatus::class),
                Tables\Filters\SelectFilter::make('payment_status')->label('Trạng thái thanh toán')->options(PaymentStatus::class),
            ])
            ->actions([
                Actions\ViewAction::make()->label('Xem'),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    static::configureOrderDeleteBulkAction(Actions\DeleteBulkAction::make()),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\OrderItemsRelationManager::class,
            RelationManagers\OrderTimelinesRelationManager::class,
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return $record instanceof Order && $record->isAdminDeletable();
    }

    public static function configureOrderDeleteBulkAction(Actions\DeleteBulkAction $action): Actions\DeleteBulkAction
    {
        return $action
            ->before(function (\Illuminate\Database\Eloquent\Collection $records, Actions\DeleteBulkAction $action): void {
                foreach ($records as $record) {
                    if (! $record instanceof Order || ! $record->isAdminDeletable()) {
                        Notification::make()
                            ->title('Không thể xóa')
                            ->body('Chỉ xóa được đơn Đã xác nhận với trạng thái thanh toán Chờ thanh toán.')
                            ->danger()
                            ->send();
                        $action->halt();

                        return;
                    }
                }

                $inventory = app(OrderInventoryService::class);
                foreach ($records as $record) {
                    if ($record instanceof Order) {
                        $inventory->releaseReservedForOrder($record);
                    }
                }
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'view' => Pages\ViewOrder::route('/{record}'),
        ];
    }
}
