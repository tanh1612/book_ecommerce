<?php

namespace App\Filament\Resources\BookResource\Pages;

use App\Filament\Resources\BookResource;
use App\Models\Book;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ListBooks extends ListRecords
{
    protected static string $resource = BookResource::class;

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Tất cả')
                ->badge(static fn (): int => Book::query()->count())
                ->badgeColor('success')
                ->deferBadge(),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\ImportAction::make()
                ->importer(\App\Filament\Imports\BookImporter::class)
                ->label('Nhập CSV')
                ->color('info')
                ->icon('heroicon-o-arrow-down-tray')
                ->chunkSize(50)
                ->maxRows(20_000),
            Actions\CreateAction::make(),
        ];
    }
}
