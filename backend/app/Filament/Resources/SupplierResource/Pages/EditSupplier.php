<?php

namespace App\Filament\Resources\SupplierResource\Pages;

use App\Filament\Resources\SupplierResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSupplier extends EditRecord
{
    protected static string $resource = SupplierResource::class;

    public function getMaxContentWidth(): string | null
    {
        return 'full';
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->before(function (\App\Models\Supplier $record, Actions\DeleteAction $action) {
                    if ($record->books()->exists()) {
                        \Filament\Notifications\Notification::make()
                            ->title('Không thể xóa')
                            ->body("Nhà cung cấp \"{$record->name}\" đang có {$record->books()->count()} sách liên kết.")
                            ->danger()
                            ->send();
                        $action->halt();
                    }
                }),
        ];
    }
}
