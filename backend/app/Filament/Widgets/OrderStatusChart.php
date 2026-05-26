<?php

namespace App\Filament\Widgets;

use App\Enums\Order\OrderStatus;
use App\Models\Order;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Collection;

class OrderStatusChart extends ChartWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 1;

    protected ?string $heading = 'Đơn hàng theo trạng thái';

    protected ?string $description = 'Số lượng đơn ở từng trạng thái xử lý hiện tại.';

    protected ?string $maxHeight = '300px';

    protected function getType(): string
    {
        return 'doughnut';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $counts = Order::query()
            ->selectRaw('current_status, COUNT(*) as aggregate')
            ->groupBy('current_status')
            ->pluck('aggregate', 'current_status');

        $statuses = collect(OrderStatus::cases());

        return [
            'datasets' => [
                [
                    'label' => 'Số đơn',
                    'data' => $statuses
                        ->map(fn (OrderStatus $status): int => (int) ($counts[$status->value] ?? 0))
                        ->all(),
                    'backgroundColor' => $this->statusColors($statuses),
                    'borderWidth' => 0,
                ],
            ],
            'labels' => $statuses
                ->map(fn (OrderStatus $status): string => (string) $status->getLabel())
                ->all(),
        ];
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
            {
                cutout: '62%',
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 12,
                            boxHeight: 12
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                const value = context.parsed ?? 0;
                                return context.label + ': ' + new Intl.NumberFormat('vi-VN').format(value) + ' đơn';
                            }
                        }
                    }
                }
            }
        JS);
    }

    /**
     * @param  Collection<int, OrderStatus>  $statuses
     * @return array<int, string>
     */
    private function statusColors(Collection $statuses): array
    {
        $colors = [
            OrderStatus::PENDING->value => '#f59e0b',
            OrderStatus::CONFIRMED->value => '#0ea5e9',
            OrderStatus::PROCESSING->value => '#6366f1',
            OrderStatus::SHIPPING->value => '#64748b',
            OrderStatus::COMPLETED->value => '#10b981',
            OrderStatus::CANCELLED->value => '#f43f5e',
            OrderStatus::REFUND_EXPIRED->value => '#71717a',
        ];

        return $statuses
            ->map(fn (OrderStatus $status): string => $colors[$status->value])
            ->all();
    }
}
