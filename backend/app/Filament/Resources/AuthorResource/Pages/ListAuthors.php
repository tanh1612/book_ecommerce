<?php

namespace App\Filament\Resources\AuthorResource\Pages;

use App\Filament\Resources\AuthorResource;
use App\Models\Author;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ListAuthors extends ListRecords
{
    protected static string $resource = AuthorResource::class;

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Tất cả')
                ->badge(static fn (): int => Author::query()->count())
                ->badgeColor('success')
                ->deferBadge(),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\ImportAction::make()
                ->importer(\App\Filament\Imports\AuthorImporter::class)
                ->label('Nhập CSV')
                ->color('info')
                ->icon('heroicon-o-arrow-down-tray')
                ->chunkSize(200)
                ->maxRows(50_000),
            Actions\CreateAction::make(),
        ];
    }
}
