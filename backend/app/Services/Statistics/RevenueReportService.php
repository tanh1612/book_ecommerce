<?php

namespace App\Services\Statistics;

use App\Enums\Order\OrderStatus;
use App\Enums\Order\PaymentStatus;
use App\Models\Order;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RevenueReportService
{
    public function totalRevenueAllTime(): float
    {
        return (float) $this->completedPaidOrdersQuery()->sum('orders.final_amount');
    }

    public function dailyRevenue(CarbonInterface $date): RevenueSummary
    {
        $query = $this->completedPaidOrdersQuery()
            ->whereDate('order_completion.completed_at', $date);

        return $this->summarize($query);
    }

    public function monthlyRevenue(int $year, int $month): RevenueSummary
    {
        $query = $this->completedPaidOrdersQuery()
            ->whereYear('order_completion.completed_at', $year)
            ->whereMonth('order_completion.completed_at', $month);

        return $this->summarize($query);
    }

    public function yearlyRevenue(int $year): RevenueSummary
    {
        $query = $this->completedPaidOrdersQuery()
            ->whereYear('order_completion.completed_at', $year);

        return $this->summarize($query);
    }

    /**
     * @return Collection<int, object{period: string, revenue: float, order_count: int}>
     */
    public function dailySeries(CarbonInterface $from, CarbonInterface $to): Collection
    {
        $rows = $this->completedPaidOrdersQuery()
            ->whereBetween('order_completion.completed_at', [
                $from->copy()->startOfDay(),
                $to->copy()->endOfDay(),
            ])
            ->selectRaw('DATE(order_completion.completed_at) as period')
            ->selectRaw('COALESCE(SUM(orders.final_amount), 0) as revenue')
            ->selectRaw('COUNT(*) as order_count')
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        return $this->fillDailySeries($from, $to, $rows);
    }

    /**
     * @return Collection<int, object{period: int, revenue: float, order_count: int}>
     */
    public function monthlySeries(int $year): Collection
    {
        $rows = $this->completedPaidOrdersQuery()
            ->whereYear('order_completion.completed_at', $year)
            ->selectRaw('MONTH(order_completion.completed_at) as period')
            ->selectRaw('COALESCE(SUM(orders.final_amount), 0) as revenue')
            ->selectRaw('COUNT(*) as order_count')
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->keyBy(fn (object $row): int => (int) $row->period);

        return collect(range(1, 12))->map(function (int $month) use ($rows): object {
            $row = $rows->get($month);

            return (object) [
                'period' => $month,
                'revenue' => (float) ($row->revenue ?? 0),
                'order_count' => (int) ($row->order_count ?? 0),
            ];
        });
    }

    public function ordersInPeriod(CarbonInterface $from, CarbonInterface $to): Builder
    {
        return $this->completedPaidOrdersQuery()
            ->with(['account'])
            ->whereBetween('order_completion.completed_at', [
                $from->copy()->startOfDay(),
                $to->copy()->endOfDay(),
            ])
            ->select([
                'orders.*',
                'order_completion.completed_at as revenue_completed_at',
            ])
            ->orderByDesc('order_completion.completed_at');
    }

    public function formatVnd(float $amount): string
    {
        return number_format($amount, 0, ',', '.').' đ';
    }

    private function completedPaidOrdersQuery(): Builder
    {
        $completionSubquery = DB::table('order_timelines')
            ->selectRaw('order_id, MIN(created_at) as completed_at')
            ->where('status', OrderStatus::COMPLETED->value)
            ->groupBy('order_id');

        return Order::query()
            ->joinSub($completionSubquery, 'order_completion', function ($join): void {
                $join->on('orders.id', '=', 'order_completion.order_id');
            })
            ->where('orders.current_status', OrderStatus::COMPLETED)
            ->where('orders.payment_status', PaymentStatus::PAID);
    }

    private function summarize(Builder $query): RevenueSummary
    {
        /** @var object{total_revenue: string|float|null, order_count: int|string, total_shipping_fee: string|float|null}|null $stats */
        $stats = (clone $query)
            ->selectRaw('COALESCE(SUM(orders.final_amount), 0) as total_revenue')
            ->selectRaw('COUNT(*) as order_count')
            ->selectRaw('COALESCE(SUM(orders.shipping_fee), 0) as total_shipping_fee')
            ->first();

        if ($stats === null) {
            return RevenueSummary::empty();
        }

        $orderCount = (int) $stats->order_count;
        $totalRevenue = (float) $stats->total_revenue;

        return new RevenueSummary(
            totalRevenue: $totalRevenue,
            orderCount: $orderCount,
            averageOrderValue: $orderCount > 0 ? $totalRevenue / $orderCount : 0,
            totalShippingFee: (float) $stats->total_shipping_fee,
        );
    }

    /**
     * @param  Collection<int, object>  $rows
     * @return Collection<int, object{period: string, revenue: float, order_count: int}>
     */
    private function fillDailySeries(CarbonInterface $from, CarbonInterface $to, Collection $rows): Collection
    {
        $indexed = $rows->keyBy(fn (object $row): string => (string) $row->period);

        $period = $from->copy()->startOfDay();
        $end = $to->copy()->startOfDay();
        $filled = collect();

        while ($period->lte($end)) {
            $key = $period->toDateString();
            $row = $indexed->get($key);

            $filled->push((object) [
                'period' => $key,
                'revenue' => (float) ($row->revenue ?? 0),
                'order_count' => (int) ($row->order_count ?? 0),
            ]);

            $period->addDay();
        }

        return $filled;
    }
}
