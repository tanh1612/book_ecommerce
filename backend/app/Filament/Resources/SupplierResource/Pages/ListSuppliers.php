<?php

namespace App\Filament\Resources\SupplierResource\Pages;

use App\Filament\Resources\SupplierResource;
use App\Models\Supplier;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ListSuppliers extends ListRecords
{
    protected static string $resource = SupplierResource::class;

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Tất cả')
                ->badge(static fn (): int => Supplier::query()->count())
                ->badgeColor('success')
                ->deferBadge(),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\ImportAction::make()
                ->importer(\App\Filament\Imports\SupplierImporter::class)
                ->label('Nhập CSV')
                ->color('info')
                ->icon('heroicon-o-arrow-down-tray')
                ->chunkSize(200)
                ->maxRows(50_000),
            Actions\CreateAction::make(),
        ];
    }
}
