<?php

namespace App\Filament\Resources\PublisherResource\Pages;

use App\Filament\Resources\PublisherResource;
use App\Models\Publisher;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ListPublishers extends ListRecords
{
    protected static string $resource = PublisherResource::class;

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Tất cả')
                ->badge(static fn (): int => Publisher::query()->count())
                ->badgeColor('success')
                ->deferBadge(),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\ImportAction::make()
                ->importer(\App\Filament\Imports\PublisherImporter::class)
                ->label('Nhập CSV')
                ->color('info')
                ->icon('heroicon-o-arrow-down-tray')
                ->chunkSize(200)
                ->maxRows(50_000),
            Actions\CreateAction::make(),
        ];
    }
}
