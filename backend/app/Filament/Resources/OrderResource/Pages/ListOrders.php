<?php

namespace App\Filament\Resources\OrderResource\Pages;

use App\Enums\Order\OrderStatus;
use App\Filament\Resources\OrderResource;
use App\Models\Order;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $tabs = [
            'all' => Tab::make('Tất cả')
                ->badge(static fn (): int => Order::query()->count())
                ->badgeColor('primary')
                ->deferBadge(),
        ];

        foreach (OrderStatus::cases() as $status) {
            $tabs[$status->value] = Tab::make($status->getLabel())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('current_status', $status))
                ->badge(static fn (): int => Order::query()->where('current_status', $status)->count())
                ->badgeColor($status->getColor())
                ->deferBadge();
        }

        return $tabs;
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
