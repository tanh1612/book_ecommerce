<?php

namespace App\Filament\Resources\InventoryResource\Pages;

use App\Filament\Resources\InventoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListInventories extends ListRecords
{
    protected static string $resource = InventoryResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Tồn kho';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Thêm tồn kho'),
        ];
    }
}
