<?php

namespace App\Filament\Resources\AuthorResource\Pages;

use App\Filament\Resources\AuthorResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAuthor extends EditRecord
{
    protected static string $resource = AuthorResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->before(function (\App\Models\Author $record, Actions\DeleteAction $action) {
                    if ($record->books()->exists()) {
                        \Filament\Notifications\Notification::make()
                            ->title('Không thể xóa')
                            ->body("Tác giả \"{$record->name}\" đang có {$record->books()->count()} sách liên kết.")
                            ->danger()
                            ->send();
                        $action->halt();
                    }
                }),
        ];
    }
}
