<x-filament-panels::page>
    @php
        $widgetKey = "revenue-{$reportType}-{$date}-{$month}-{$year}";
        $periodLabel = $this->getReportPeriodLabel();
        $widgetData = [
            'reportType' => $reportType,
            'date' => $date,
            'month' => $month,
            'year' => $year,
        ];
    @endphp

    <x-filament::section
        heading="Bộ lọc báo cáo"
        :description="'Kỳ ' . $periodLabel . ' · Chỉ tính đơn hoàn tất và đã thanh toán'"
    >
        {{ $this->filtersForm }}
    </x-filament::section>

    <x-filament-widgets::widgets
        :columns="1"
        :widgets="[
            'stats-' . $widgetKey => \App\Filament\Widgets\RevenueSummaryStats::make($widgetData),
            'chart-' . $widgetKey => \App\Filament\Widgets\RevenueTrendChart::make([
                ...$widgetData,
                'periodLabel' => $periodLabel,
            ]),
        ]"
    />

    {{ $this->table }}
</x-filament-panels::page>
