<?php

namespace App\Filament\Pages;

use App\Filament\Resources\OrderResource;
use App\Models\Order;
use App\Services\Statistics\RevenueReportService;
use App\Services\Statistics\RevenueSummary;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

class RevenueReport extends Page implements HasForms, HasTable
{
    use InteractsWithForms;
    use InteractsWithTable;

    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static \UnitEnum|string|null $navigationGroup = 'Thống kê';

    protected static ?string $navigationLabel = 'Doanh thu';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Thống kê doanh thu';

    protected string $view = 'filament.pages.revenue-report';

    public string $reportType = 'monthly';

    public ?string $date = null;

    public int $month = 1;

    public int $year = 2026;

    /** @var array<string, mixed> */
    public array $filterData = [];

    public function mount(): void
    {
        $now = now();
        $this->year = (int) $now->year;
        $this->month = (int) $now->month;
        $this->date = $now->toDateString();
        $this->filterData = [
            'reportType' => $this->reportType,
            'date' => $this->date,
            'month' => $this->month,
            'year' => $this->year,
        ];
        $this->filtersForm->fill($this->filterData);
    }

    public function getHeading(): string|Htmlable
    {
        return 'Thống kê doanh thu';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Kỳ '.$this->getReportPeriodLabel().' · Đơn hoàn tất, đã thanh toán';
    }

    public function getReportPeriodLabel(): string
    {
        return match ($this->reportType) {
            'daily' => 'Ngày '.Carbon::parse($this->date ?? now())->format('d/m/Y'),
            'monthly' => 'Tháng '.sprintf('%02d', $this->month).'/'.$this->year,
            'yearly' => 'Năm '.$this->year,
            default => '',
        };
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->columns([
                'default' => 1,
                'sm' => 2,
                'lg' => 4,
            ])
            ->components([
                Select::make('reportType')
                    ->label('Kỳ báo cáo')
                    ->options([
                        'daily' => 'Theo ngày',
                        'monthly' => 'Theo tháng',
                        'yearly' => 'Theo năm',
                    ])
                    ->native(false)
                    ->live()
                    ->afterStateUpdated(function (): void {
                        $this->normalizeFilterData();
                        $this->filtersForm->fill($this->filterData);
                    }),
                DatePicker::make('date')
                    ->label('Ngày')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->maxDate(now())
                    ->visible(fn (): bool => ($this->filterData['reportType'] ?? $this->reportType) === 'daily')
                    ->live(),
                Select::make('month')
                    ->label('Tháng')
                    ->options(collect(range(1, 12))->mapWithKeys(fn (int $m): array => [
                        $m => 'Tháng '.$m,
                    ])->all())
                    ->native(false)
                    ->visible(fn (): bool => ($this->filterData['reportType'] ?? $this->reportType) === 'monthly')
                    ->live(),
                Select::make('year')
                    ->label('Năm')
                    ->options(collect(range(now()->year - 5, now()->year))
                        ->mapWithKeys(fn (int $y): array => [$y => (string) $y])
                        ->all())
                    ->native(false)
                    ->visible(fn (): bool => in_array($this->filterData['reportType'] ?? $this->reportType, ['monthly', 'yearly'], true))
                    ->live(),
            ])
            ->statePath('filterData');
    }

    public function updatedFilterData(): void
    {
        $this->normalizeFilterData();
        $this->syncFiltersFromForm();
        $this->resetTable();
    }

    /**
     * @return array<int, string>
     */
    protected function getForms(): array
    {
        return [
            'filtersForm',
        ];
    }

    public function getSummary(): RevenueSummary
    {
        $service = app(RevenueReportService::class);

        return match ($this->reportType) {
            'daily' => $service->dailyRevenue(Carbon::parse($this->date ?? now())),
            'monthly' => $service->monthlyRevenue($this->year, $this->month),
            'yearly' => $service->yearlyRevenue($this->year),
            default => RevenueSummary::empty(),
        };
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public function getChartPeriodBounds(): array
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
            'yearly' => [
                Carbon::create($this->year, 1, 1)->startOfYear(),
                Carbon::create($this->year, 12, 31)->endOfYear(),
            ],
            default => [now()->startOfMonth(), now()->endOfMonth()],
        };
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public function getTablePeriodBounds(): array
    {
        return match ($this->reportType) {
            'daily' => [
                Carbon::parse($this->date ?? now())->startOfDay(),
                Carbon::parse($this->date ?? now())->endOfDay(),
            ],
            'monthly' => [
                Carbon::create($this->year, $this->month, 1)->startOfMonth(),
                Carbon::create($this->year, $this->month, 1)->endOfMonth(),
            ],
            'yearly' => [
                Carbon::create($this->year, 1, 1)->startOfYear(),
                Carbon::create($this->year, 12, 31)->endOfYear(),
            ],
            default => [now()->startOfDay(), now()->endOfDay()],
        };
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Đối soát đơn hàng')
            ->description(fn (): string => 'Kỳ '.$this->getReportPeriodLabel().' · Đơn hoàn tất và đã thanh toán')
            ->query(function (): Builder {
                [$from, $to] = $this->getTablePeriodBounds();

                return app(RevenueReportService::class)->ordersInPeriod($from, $to);
            })
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('Mã đơn')
                    ->prefix('#')
                    ->searchable()
                    ->sortable()
                    ->color('primary')
                    ->weight('medium')
                    ->url(fn (Order $record): string => OrderResource::getUrl('view', ['record' => $record])),
                Tables\Columns\TextColumn::make('account.email')
                    ->label('Khách hàng')
                    ->searchable()
                    ->limit(35)
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('revenue_completed_at')
                    ->label('Ngày hoàn tất')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('payment_method')
                    ->label('Thanh toán')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => match ((string) ($state?->value ?? $state)) {
                        'cod' => 'COD',
                        'vnpay' => 'VNPay',
                        default => (string) ($state?->getLabel() ?? $state ?? '—'),
                    }),
                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Tổng hàng')
                    ->money('VND')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('shipping_fee')
                    ->label('Phí vận chuyển')
                    ->money('VND')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('final_amount')
                    ->label('Tổng thu')
                    ->money('VND')
                    ->sortable(),
            ])
            ->defaultSort('revenue_completed_at', 'desc')
            ->recordActions([
                Actions\ViewAction::make()
                    ->label('Chi tiết')
                    ->url(fn (Order $record): string => OrderResource::getUrl('view', ['record' => $record])),
            ])
            ->recordActionsColumnLabel('Thao tác')
            ->emptyStateHeading('Không có đơn trong kỳ này')
            ->emptyStateDescription('Chưa có đơn hoàn tất và đã thanh toán phù hợp bộ lọc. Thử chọn kỳ khác hoặc kiểm tra trạng thái đơn trên hệ thống.')
            ->emptyStateIcon('heroicon-o-clipboard-document-list')
            ->paginated([10, 25, 50]);
    }

    private function syncFiltersFromForm(): void
    {
        $this->reportType = (string) ($this->filterData['reportType'] ?? 'monthly');
        $this->date = isset($this->filterData['date'])
            ? Carbon::parse($this->filterData['date'])->toDateString()
            : now()->toDateString();
        $this->month = (int) ($this->filterData['month'] ?? now()->month);
        $this->year = (int) ($this->filterData['year'] ?? now()->year);
    }

    private function normalizeFilterData(): void
    {
        $now = now();

        $this->filterData['reportType'] = (string) ($this->filterData['reportType'] ?? 'monthly');
        $this->filterData['date'] = isset($this->filterData['date'])
            ? Carbon::parse($this->filterData['date'])->toDateString()
            : $now->toDateString();
        $this->filterData['month'] = max(1, min(12, (int) ($this->filterData['month'] ?? $now->month)));
        $this->filterData['year'] = (int) ($this->filterData['year'] ?? $now->year);

        if ($this->filterData['year'] > $now->year) {
            $this->filterData['year'] = $now->year;
        }

        if (
            $this->filterData['reportType'] === 'daily'
            && Carbon::parse($this->filterData['date'])->isAfter($now)
        ) {
            $this->filterData['date'] = $now->toDateString();
        }
    }
}
