<?php

namespace App\Filament\Resources;

use App\Enums\Order\OrderStatus;
use App\Enums\Order\PaymentMethod;
use App\Enums\Order\PaymentStatus;
use App\Filament\Resources\OrderResource\Pages;
use App\Filament\Resources\OrderResource\RelationManagers;
use App\Models\Order;
use Filament\Actions;
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
            ])->columns(3),
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
            ->actions([Actions\EditAction::make()])
            ->bulkActions([Actions\BulkActionGroup::make([Actions\DeleteBulkAction::make()])]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\OrderItemsRelationManager::class,
            RelationManagers\OrderTimelinesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'view' => Pages\ViewOrder::route('/{record}'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }
}
