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
use Filament\Tables\Table;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-shopping-bag';
    protected static \UnitEnum|string|null $navigationGroup = 'Sales';
    protected static ?int $navigationSort = 1;
    protected static ?string $navigationLabel = 'Orders';
    protected static ?string $modelLabel = 'Order';
    protected static ?string $pluralModelLabel = 'Orders';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Layout\Section::make('Order Information')->components([
                Field\Select::make('account_id')->label('Customer')->relationship('account', 'email')->searchable()->preload()->required(),
                Field\Select::make('current_status')->label('Status')->options([
                    'pending' => 'Pending',
                    'confirmed' => 'Confirmed',
                    'processing' => 'Processing',
                    'shipping' => 'Shipping',
                    'delivered' => 'Delivered',
                    'cancelled' => 'Cancelled',
                ])->required(),
                Field\Select::make('payment_method')->label('Payment')->options([
                    'cod' => 'COD',
                    'bank_transfer' => 'Bank Transfer',
                    'momo' => 'MoMo',
                ]),
            ])->columns(3),
            Layout\Section::make('Shipping & Totals')->components([
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
                Tables\Columns\TextColumn::make('created_at')->label('Created At')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('current_status')->label('Status')->options([
                    'pending' => 'Pending', 'confirmed' => 'Confirmed', 'processing' => 'Processing',
                    'shipping' => 'Shipping', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled',
                ]),
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
