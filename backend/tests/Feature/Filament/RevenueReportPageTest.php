<?php

use App\Enums\Account\AccountRole;
use App\Filament\Pages\RevenueReport;
use App\Filament\Widgets\RevenueSummaryStats;
use App\Filament\Widgets\RevenueTrendChart;
use App\Models\Account;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('admin can access revenue report page with main labels', function (): void {
    $admin = Account::factory()->create(['role' => AccountRole::Admin]);

    Livewire::actingAs($admin)
        ->test(RevenueReport::class)
        ->assertSuccessful()
        ->assertSee('Thống kê doanh thu')
        ->assertSee('Bộ lọc báo cáo')
        ->assertSee('Kỳ báo cáo')
        ->assertSee('Đối soát đơn hàng')
        ->assertSee('Tổng thu')
        ->assertSee('Kỳ');
});

test('revenue summary widget renders main stat labels', function (): void {
    Livewire::test(RevenueSummaryStats::class, [
        'reportType' => 'monthly',
        'date' => now()->toDateString(),
        'month' => now()->month,
        'year' => now()->year,
    ])
        ->assertSee('Tổng doanh thu')
        ->assertSee('Số đơn hoàn tất')
        ->assertSee('Giá trị đơn trung bình')
        ->assertSee('Phí vận chuyển đã thu');
});

test('revenue report filter changes report period label', function (): void {
    $admin = Account::factory()->create(['role' => AccountRole::Admin]);

    Livewire::actingAs($admin)
        ->test(RevenueReport::class)
        ->set('filterData.reportType', 'yearly')
        ->set('filterData.year', 2025)
        ->assertSet('reportType', 'yearly')
        ->assertSet('year', 2025)
        ->assertSee('Năm 2025');
});

test('revenue trend chart heading matches report type', function (): void {
    Livewire::test(RevenueTrendChart::class, [
        'reportType' => 'daily',
        'date' => now()->toDateString(),
        'month' => now()->month,
        'year' => now()->year,
        'periodLabel' => 'Ngày '.now()->format('d/m/Y'),
    ])
        ->assertSee('Doanh thu theo ngày trong tháng');

    Livewire::test(RevenueTrendChart::class, [
        'reportType' => 'monthly',
        'date' => now()->toDateString(),
        'month' => now()->month,
        'year' => now()->year,
        'periodLabel' => 'Tháng '.sprintf('%02d', now()->month).'/'.now()->year,
    ])
        ->assertSee('Doanh thu theo ngày');

    Livewire::test(RevenueTrendChart::class, [
        'reportType' => 'yearly',
        'date' => now()->toDateString(),
        'month' => now()->month,
        'year' => 2025,
        'periodLabel' => 'Năm 2025',
    ])
        ->assertSee('Doanh thu theo tháng');
});
