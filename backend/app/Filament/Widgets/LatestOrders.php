<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class LatestOrders extends BaseWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Đơn hàng gần đây';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Order::query()->latest()->limit(5)
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
                    ->label('Trạng thái')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'pending' => 'warning',
                        'confirmed', 'processing' => 'info',
                        'shipping' => 'primary',
                        'delivered' => 'success',
                        'cancelled' => 'danger',
                        default => 'secondary',
                    })
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'pending' => 'Chờ xử lý',
                        'confirmed' => 'Đã xác nhận',
                        'processing' => 'Đang xử lý',
                        'shipping' => 'Đang giao hàng',
                        'delivered' => 'Đã giao hàng',
                        'cancelled' => 'Đã hủy',
                        'returned' => 'Trả hàng',
                        default => $state ?? '—',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Ngày đặt')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->paginated(false);
    }
}
