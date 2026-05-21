<?php

namespace App\Filament\Concerns;

use App\Models\Inventory;
use App\Services\Inventory\InventoryRestockService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

trait CreatesInventoryViaRestockService
{
    /**
     * @param  array<string, mixed>  $data
     */
    protected function createInventoryViaRestock(array $data): Inventory
    {
        try {
            $result = app(InventoryRestockService::class)->createOrRestock($data);

            if ($result->restocked) {
                Notification::make()
                    ->title('Đã cộng dồn tồn kho cho sách hiện có')
                    ->success()
                    ->send();
            }

            return $result->inventory;
        } catch (ValidationException $e) {
            $this->failInventoryRestockValidation($e);
        }
    }

    protected function failInventoryRestockValidation(ValidationException $e): never
    {
        $message = collect($e->errors())->flatten()->first() ?? 'Dữ liệu không hợp lệ.';

        Notification::make()
            ->title('Không thể lưu tồn kho')
            ->body($message)
            ->danger()
            ->send();

        $prefix = $this instanceof CreateRecord ? 'data.' : '';

        throw ValidationException::withMessages(
            collect($e->errors())->mapWithKeys(
                fn (array $messages, string $key): array => [$prefix.$key => $messages],
            )->all(),
        );
    }
}
