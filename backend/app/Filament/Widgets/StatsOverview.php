<?php

namespace App\Filament\Widgets;

use App\Models\Account;
use App\Models\Book;
use App\Models\Order;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected static ?int $sort = 0;

    protected function getStats(): array
    {
        return [
            Stat::make('Tổng đơn hàng', Order::count())
                ->description('Tất cả đơn hàng đã đặt')
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->color('primary'),
            Stat::make('Doanh thu', number_format(Order::where('current_status', 'delivered')->sum('final_amount'), 0, ',', '.') . '₫')
                ->description('Đơn hàng đã giao thành công')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),
            Stat::make('Sách', Book::where('is_active', true)->count())
                ->description('Sách đang hoạt động')
                ->descriptionIcon('heroicon-m-book-open')
                ->color('info'),
            Stat::make('Khách hàng', Account::where('role', 'customer')->count())
                ->description('Tổng số tài khoản khách hàng')
                ->descriptionIcon('heroicon-m-users')
                ->color('warning'),
        ];
    }
}
