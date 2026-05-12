<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Models\Order;
use Filament\Forms\Components as Field;
use Filament\Schemas\Components as Layout;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Actions;
use Filament\Tables;
use App\Enums\Order\OrderStatus;
use App\Enums\Order\PaymentMethod;
use App\Enums\Order\PaymentStatus;
use Filament\Tables\Table;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-shopping-bag';
    protected static \UnitEnum|string|null $navigationGroup = 'Kinh doanh';
    protected static ?int $navigationSort = 1;
    protected static ?string $navigationLabel = 'Đơn hàng';
    protected static ?string $modelLabel = 'Đơn hàng';
    protected static ?string $pluralModelLabel = 'Đơn hàng';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Layout\Section::make('Thông tin đơn hàng')->components([
                Field\Select::make('account_id')->label('Customer')->relationship('account', 'email')->searchable()->preload()->required(),
                Field\Select::make('current_status')->label('Status')->options(OrderStatus::class)->required(),
                Field\Select::make('payment_method')->label('Payment')->options(PaymentMethod::class),
                Field\Select::make('payment_status')->label('Payment Status')->options(PaymentStatus::class),
            ])->columns(3),
            Layout\Section::make('Giao hàng & Tổng cộng')->components([
                Field\TextInput::make('shipping_name')->label('Recipient Name')->maxLength(255),
                Field\TextInput::make('shipping_phone')->label('Phone')->maxLength(20),
                Field\TextInput::make('final_amount')->label('Total Amount')->numeric()->prefix('₫'),
            ])->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->label('Order ID')->prefix('#')->sortable(),
                Tables\Columns\TextColumn::make('account.email')->label('Customer')->searchable()->limit(25),
                Tables\Columns\TextColumn::make('final_amount')->label('Total Amount')->money('VND')->sortable(),
                Tables\Columns\TextColumn::make('current_status')->label('Status')->badge(),
                Tables\Columns\TextColumn::make('payment_status')->label('Payment Status')->badge(),
                Tables\Columns\TextColumn::make('created_at')->label('Created At')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('current_status')->label('Status')->options(OrderStatus::class),
                Tables\Filters\SelectFilter::make('payment_status')->label('Payment Status')->options(PaymentStatus::class),
            ])
            ->actions([Actions\EditAction::make()])
            ->bulkActions([Actions\BulkActionGroup::make([Actions\DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }
}
