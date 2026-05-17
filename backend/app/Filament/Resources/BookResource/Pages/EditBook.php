<?php

namespace App\Filament\Resources\BookResource\Pages;

use App\Filament\Resources\BookResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditBook extends EditRecord
{
    protected static string $resource = BookResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->before(function (Actions\DeleteAction $action, \App\Models\Book $record) {
                    $hasOrders = \Illuminate\Support\Facades\DB::table('order_items')->where('book_id', $record->id)->exists();
                    if ($hasOrders) {
                        \Filament\Notifications\Notification::make()
                            ->danger()
                            ->title('Thao tác thất bại')
                            ->body('Không thể xóa sách này vì đã tồn tại trong đơn hàng.')
                            ->send();

                        $action->halt();
                    }
                }),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
