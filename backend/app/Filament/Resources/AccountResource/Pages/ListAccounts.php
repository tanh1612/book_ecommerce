<?php

namespace App\Filament\Resources\AccountResource\Pages;

use App\Enums\Account\AccountRole;
use App\Filament\Resources\AccountResource;
use App\Models\Account;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListAccounts extends ListRecords
{
    protected static string $resource = AccountResource::class;

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Tất cả')
                ->badge(static fn (): int => Account::query()->count())
                ->badgeColor('primary')
                ->deferBadge(),
            'customers' => Tab::make('Khách hàng')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('role', AccountRole::Customer))
                ->badge(static fn (): int => Account::query()->where('role', AccountRole::Customer)->count())
                ->badgeColor('success')
                ->deferBadge(),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
