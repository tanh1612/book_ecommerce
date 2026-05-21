<?php

namespace App\Filament\Resources\InventoryResource\Pages;

use App\Filament\Concerns\CreatesInventoryViaRestockService;
use App\Filament\Resources\InventoryResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;

class CreateInventory extends CreateRecord
{
    use CreatesInventoryViaRestockService;

    protected static string $resource = InventoryResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Thêm tồn kho';
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return $this->createInventoryViaRestock($data);
    }
}
