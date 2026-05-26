<?php

namespace App\Filament\Resources\PromotionResource\Pages;

use App\Enums\Promotion\PromotionStatus;
use App\Filament\Resources\PromotionResource;
use App\Models\Promotion;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListPromotions extends ListRecords
{
    protected static string $resource = PromotionResource::class;

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $tabs = [
            'all' => Tab::make('Tất cả')
                ->badge(static fn (): int => Promotion::query()->count())
                ->badgeColor('primary')
                ->deferBadge(),
        ];

        foreach (PromotionStatus::cases() as $status) {
            $tabs[$status->value] = Tab::make($status->getLabel())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', $status))
                ->badge(static fn (): int => Promotion::query()->where('status', $status)->count())
                ->badgeColor($status->getColor())
                ->deferBadge();
        }

        return $tabs;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
