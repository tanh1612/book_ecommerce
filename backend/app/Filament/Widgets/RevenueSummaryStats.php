<?php

namespace App\Filament\Widgets;

use App\Services\Statistics\RevenueReportService;
use App\Services\Statistics\RevenueSummary;
use Carbon\Carbon;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class RevenueSummaryStats extends BaseWidget
{
    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 0;

    public string $reportType = 'monthly';

    public ?string $date = null;

    public int $month = 1;

    public int $year = 2026;

    protected function getColumns(): int|array
    {
        return [
            'default' => 1,
            'md' => 2,
            'xl' => 4,
        ];
    }

    protected function getStats(): array
    {
        $summary = $this->resolveSummary();
        $formatter = app(RevenueReportService::class);

        return [
            Stat::make('Tổng doanh thu', $formatter->formatVnd($summary->totalRevenue))
                ->description('Tổng thu đã thanh toán')
                ->descriptionIcon('heroicon-m-banknotes')
                ->icon('heroicon-m-banknotes')
                ->color('success'),
            Stat::make('Số đơn hoàn tất', number_format($summary->orderCount, 0, ',', '.'))
                ->description('Đơn trong kỳ đã chọn')
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->icon('heroicon-m-shopping-bag')
                ->color('primary'),
            Stat::make('Giá trị đơn trung bình', $formatter->formatVnd($summary->averageOrderValue))
                ->description('Tổng thu chia số đơn')
                ->descriptionIcon('heroicon-m-calculator')
                ->icon('heroicon-m-calculator')
                ->color('info'),
            Stat::make('Phí vận chuyển đã thu', $formatter->formatVnd($summary->totalShippingFee))
                ->description('Phí ship trong tổng thu')
                ->descriptionIcon('heroicon-m-truck')
                ->icon('heroicon-m-truck')
                ->color('gray'),
        ];
    }

    private function resolveSummary(): RevenueSummary
    {
        $service = app(RevenueReportService::class);

        return match ($this->reportType) {
            'daily' => $service->dailyRevenue(Carbon::parse($this->date ?? now())),
            'monthly' => $service->monthlyRevenue($this->year, $this->month),
            'yearly' => $service->yearlyRevenue($this->year),
            default => RevenueSummary::empty(),
        };
    }
}
