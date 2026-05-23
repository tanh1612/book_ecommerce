<?php

namespace App\Filament\Resources\InventoryResource\Pages;

use App\Filament\Resources\InventoryResource;
use App\Services\Inventory\LowStockAlertService;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

class ListInventories extends ListRecords
{
    protected static string $resource = InventoryResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Tồn kho';
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Tất cả'),
            'low' => Tab::make('Sắp hết hàng')
                ->modifyQueryUsing(fn (Builder $query): Builder => LowStockAlertService::applyLowStockScope($query))
                ->badge(fn (): int => app(LowStockAlertService::class)->countLowStockBooks()),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Thêm tồn kho'),
        ];
    }
}
