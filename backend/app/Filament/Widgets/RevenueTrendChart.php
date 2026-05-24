<?php

namespace App\Filament\Widgets;

use App\Services\Statistics\RevenueReportService;
use Carbon\Carbon;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;

class RevenueTrendChart extends ChartWidget
{
    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '280px';

    protected string $view = 'filament.widgets.revenue-trend-chart';

    public string $reportType = 'monthly';

    public ?string $date = null;

    public int $month = 1;

    public int $year = 2026;

    public string $periodLabel = '';

    public function getHeading(): string|Htmlable|null
    {
        return match ($this->reportType) {
            'daily' => 'Doanh thu theo ngày trong tháng',
            'monthly' => 'Doanh thu theo ngày',
            'yearly' => 'Doanh thu theo tháng',
            default => 'Xu hướng doanh thu',
        };
    }

    public function getDescription(): ?string
    {
        $period = $this->periodLabel !== '' ? 'Kỳ '.$this->periodLabel.' · ' : '';

        $scope = match ($this->reportType) {
            'daily' => 'Biểu đồ cả tháng chứa ngày đã chọn; KPI và bảng chỉ tính đúng ngày đó.',
            default => 'Đơn hoàn tất và đã thanh toán.',
        };

        return $period.$scope;
    }

    public function hasChartRevenue(): bool
    {
        $values = $this->getCachedData()['datasets'][0]['data'] ?? [];

        return array_sum(array_map('floatval', $values)) > 0;
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $service = app(RevenueReportService::class);

        if ($this->reportType === 'yearly') {
            $series = $service->monthlySeries($this->year);
            $labels = $series->map(fn (object $row): string => 'T'.$row->period)->all();
            $data = $series->map(fn (object $row): float => $row->revenue)->all();
            $orderCounts = $series->map(fn (object $row): int => $row->order_count)->all();
        } else {
            [$from, $to] = $this->chartPeriodBounds();
            $series = $service->dailySeries($from, $to);
            $labels = $series->map(fn (object $row): string => Carbon::parse($row->period)->format('d/m'))->all();
            $data = $series->map(fn (object $row): float => $row->revenue)->all();
            $orderCounts = $series->map(fn (object $row): int => $row->order_count)->all();
        }

        return [
            'datasets' => [
                [
                    'label' => 'Doanh thu',
                    'data' => $data,
                    'orderCounts' => $orderCounts,
                    'borderColor' => 'rgb(79, 70, 229)',
                    'backgroundColor' => 'rgba(79, 70, 229, 0.65)',
                    'borderWidth' => 1,
                    'borderRadius' => 6,
                    'borderSkipped' => false,
                    'barPercentage' => 0.72,
                    'categoryPercentage' => 0.72,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
            {
                maintainAspectRatio: false,
                resizeDelay: 120,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                const value = context.parsed.y ?? 0;
                                const lines = [
                                    'Doanh thu: ' + new Intl.NumberFormat('vi-VN').format(value) + ' đ'
                                ];
                                const orderCounts = context.dataset.orderCounts;
                                if (orderCounts && orderCounts[context.dataIndex] !== undefined) {
                                    const count = orderCounts[context.dataIndex];
                                    lines.push('Số đơn: ' + new Intl.NumberFormat('vi-VN').format(count));
                                }
                                return lines;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        ticks: {
                            autoSkip: true,
                            maxTicksLimit: 12,
                            maxRotation: 45,
                            minRotation: 0
                        },
                        grid: {
                            display: false
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grace: '8%',
                        ticks: {
                            maxTicksLimit: 6,
                            callback: function (value) {
                                if (value >= 1000000000) {
                                    return (value / 1000000000).toFixed(1) + ' tỷ';
                                }
                                if (value >= 1000000) {
                                    return (value / 1000000).toFixed(1) + ' tr';
                                }
                                if (value >= 1000) {
                                    return (value / 1000).toFixed(0) + ' k';
                                }
                                return value;
                            }
                        }
                    }
                }
            }
        JS);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function chartPeriodBounds(): array
    {
        return match ($this->reportType) {
            'daily' => [
                Carbon::parse($this->date ?? now())->startOfMonth(),
                Carbon::parse($this->date ?? now())->endOfMonth(),
            ],
            'monthly' => [
                Carbon::create($this->year, $this->month, 1)->startOfMonth(),
                Carbon::create($this->year, $this->month, 1)->endOfMonth(),
            ],
            default => [now()->startOfMonth(), now()->endOfMonth()],
        };
    }
}
