<?php

namespace App\Filament\Resources\PublisherResource\Pages;

use App\Filament\Resources\PublisherResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPublisher extends EditRecord
{
    protected static string $resource = PublisherResource::class;

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
                ->before(function (\App\Models\Publisher $record, Actions\DeleteAction $action) {
                    if ($record->books()->exists()) {
                        \Filament\Notifications\Notification::make()
                            ->title('Không thể xóa')
                            ->body("Nhà xuất bản \"{$record->name}\" đang có {$record->books()->count()} sách liên kết.")
                            ->danger()
                            ->send();
                        $action->halt();
                    }
                }),
        ];
    }
}
