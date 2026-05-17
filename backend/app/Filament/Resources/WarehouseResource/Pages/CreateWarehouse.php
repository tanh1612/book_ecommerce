<?php

namespace App\Filament\Resources\WarehouseResource\Pages;

use App\Filament\Resources\WarehouseResource;
use App\Models\Warehouse;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;

class CreateWarehouse extends CreateRecord
{
    protected static string $resource = WarehouseResource::class;

    protected function beforeCreate(): void
    {
        if (Warehouse::query()->exists()) {
            Notification::make()
                ->danger()
                ->title('Không thể tạo thêm kho')
                ->body('Hệ thống chỉ hỗ trợ một kho duy nhất.')
                ->send();

            throw (new Halt)->rollBackDatabaseTransaction(false);
        }
    }
}
