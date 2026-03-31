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
            Stat::make('Total Orders', Order::count())
                ->description('All orders placed')
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->color('primary'),
            Stat::make('Revenue', number_format(Order::where('current_status', 'delivered')->sum('final_amount'), 0, ',', '.') . '₫')
                ->description('Delivered orders')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),
            Stat::make('Books', Book::where('is_active', true)->count())
                ->description('Active books for sale')
                ->descriptionIcon('heroicon-m-book-open')
                ->color('info'),
            Stat::make('Customers', Account::where('role', 'customer')->count())
                ->description('Total customer accounts')
                ->descriptionIcon('heroicon-m-users')
                ->color('warning'),
        ];
    }
}
