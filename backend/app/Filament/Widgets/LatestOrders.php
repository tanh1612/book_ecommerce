<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\OrderResource;
use App\Models\Order;
use Filament\Actions;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LatestOrders extends BaseWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Đơn hàng cần xử lý';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Order::query()
                    ->needsAdminProcessing()
                    ->with('account')
                    ->oldest('created_at')
                    ->limit(10),
            )
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('Mã đơn')
                    ->prefix('#'),
                Tables\Columns\TextColumn::make('account.email')
                    ->label('Khách hàng')
                    ->limit(25),
                Tables\Columns\TextColumn::make('final_amount')
                    ->label('Tổng tiền')
                    ->money('VND'),
                Tables\Columns\TextColumn::make('current_status')
                    ->label('Trạng thái đơn')
                    ->badge(),
                Tables\Columns\TextColumn::make('payment_status')
                    ->label('Thanh toán')
                    ->badge(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Ngày đặt')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->recordUrl(fn (Order $record): string => OrderResource::getUrl('view', ['record' => $record]))
            ->recordActions([
                Actions\ViewAction::make()
                    ->label('Xem')
                    ->url(fn (Order $record): string => OrderResource::getUrl('view', ['record' => $record])),
            ])
            ->emptyStateHeading('Không có đơn cần xử lý')
            ->emptyStateDescription('Các đơn chờ xử lý, đã xác nhận, đang xử lý hoặc đang giao sẽ hiển thị tại đây.')
            ->paginated(false);
    }
}
