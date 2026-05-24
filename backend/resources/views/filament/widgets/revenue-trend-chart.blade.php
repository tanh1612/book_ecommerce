@php
    use Filament\Widgets\View\Components\ChartWidgetComponent;
    use Illuminate\View\ComponentAttributeBag;

    $color = $this->getColor();
    $heading = $this->getHeading();
    $description = $this->getDescription();
    $filters = $this->getFilters();
    $isCollapsible = $this->isCollapsible();
    $type = $this->getType();
    $hasRevenue = $this->hasChartRevenue();
@endphp

<x-filament-widgets::widget class="fi-wi-chart">
    <x-filament::section
        :description="$description"
        :heading="$heading"
        :collapsible="$isCollapsible"
    >
        @if ($filters || method_exists($this, 'getFiltersSchema'))
            <x-slot name="afterHeader">
                @if ($filters)
                    <x-filament::input.wrapper
                        inline-prefix
                        wire:target="filter"
                        class="fi-wi-chart-filter"
                    >
                        <x-filament::input.select
                            inline-prefix
                            wire:model.live="filter"
                        >
                            @foreach ($filters as $value => $label)
                                <option value="{{ $value }}">
                                    {{ $label }}
                                </option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                @endif

                @if (method_exists($this, 'getFiltersSchema'))
                    <x-filament::dropdown
                        placement="bottom-end"
                        shift
                        width="xs"
                        class="fi-wi-chart-filter"
                    >
                        <x-slot name="trigger">
                            {{ $this->getFiltersTriggerAction() }}
                        </x-slot>

                        <div class="fi-wi-chart-filter-content">
                            {{ $this->getFiltersSchema() }}

                            @if (method_exists($this, 'hasDeferredFilters') && $this->hasDeferredFilters())
                                <div class="fi-wi-chart-filter-content-actions-ctn">
                                    {{ $this->getFiltersApplyAction() }}

                                    {{ $this->getFiltersResetAction() }}
                                </div>
                            @endif
                        </div>
                    </x-filament::dropdown>
                @endif
            </x-slot>
        @endif

        @if (! $hasRevenue)
            <div
                style="align-items: center; border: 1px dashed currentColor; border-radius: 0.5rem; display: flex; flex-direction: column; gap: 0.5rem; justify-content: center; min-height: 18rem; opacity: 0.75; padding: 3rem 1.5rem; text-align: center;"
            >
                <x-filament::icon
                    icon="heroicon-o-chart-bar"
                    class="h-10 w-10 text-gray-400 dark:text-gray-500"
                />
                <p class="text-sm font-medium text-gray-950 dark:text-white">
                    Chưa có doanh thu trong kỳ biểu đồ
                </p>
                <p class="max-w-md text-xs text-gray-500 dark:text-gray-400">
                    Không có đơn hoàn tất và đã thanh toán trong phạm vi thời gian của biểu đồ. Thử đổi kỳ báo cáo hoặc kiểm tra lại dữ liệu đơn hàng.
                </p>
            </div>
        @else
            <div
                @if ($pollingInterval = $this->getPollingInterval())
                    wire:poll.{{ $pollingInterval }}="updateChartData"
                @endif
            >
                <div
                    x-load
                    x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('chart', 'filament/widgets') }}"
                    wire:ignore
                    data-chart-type="{{ $type }}"
                    x-data="chart({
                                cachedData: @js($this->getCachedData()),
                                maxHeight: @js($maxHeight = $this->getMaxHeight()),
                                options: @js($this->getOptions()),
                                type: @js($type),
                            })"
                    {{
                        (new ComponentAttributeBag)
                            ->color(ChartWidgetComponent::class, $color)
                            ->class([
                                'fi-wi-chart-canvas-ctn',
                                'fi-wi-chart-canvas-ctn-no-aspect-ratio' => filled($maxHeight),
                            ])
                    }}
                >
                    <canvas
                        x-ref="canvas"
                        @if ($maxHeight)
                            style="height: {{ $maxHeight }}; max-height: {{ $maxHeight }}"
                        @endif
                    ></canvas>

                    <span
                        x-ref="backgroundColorElement"
                        class="fi-wi-chart-bg-color"
                    ></span>

                    <span
                        x-ref="borderColorElement"
                        class="fi-wi-chart-border-color"
                    ></span>

                    <span
                        x-ref="gridColorElement"
                        class="fi-wi-chart-grid-color"
                    ></span>

                    <span
                        x-ref="textColorElement"
                        class="fi-wi-chart-text-color"
                    ></span>
                </div>
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
