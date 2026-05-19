<?php

namespace App\Filament\Resources\InventoryResource\Pages;

use App\Filament\Resources\InventoryResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Support\Htmlable;

class CreateInventory extends CreateRecord
{
    protected static string $resource = InventoryResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Thêm tồn kho';
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
