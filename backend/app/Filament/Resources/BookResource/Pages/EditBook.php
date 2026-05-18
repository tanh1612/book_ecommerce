<?php

namespace App\Filament\Resources\BookResource\Pages;

use App\Filament\Concerns\HandlesBooksPriceCheckConstraintViolation;
use App\Filament\Resources\BookResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;

class EditBook extends EditRecord
{
    use HandlesBooksPriceCheckConstraintViolation;

    protected static string $resource = BookResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return parent::handleRecordUpdate($record, $data);
        } catch (QueryException $e) {
            $this->abortOnBooksPricesCheckConstraint($e);
        }
    }

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
