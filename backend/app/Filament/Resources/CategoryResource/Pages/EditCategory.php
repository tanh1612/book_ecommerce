<?php

namespace App\Filament\Resources\CategoryResource\Pages;

use App\Filament\Resources\CategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCategory extends EditRecord
{
    protected static string $resource = CategoryResource::class;

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
                ->before(function (\App\Models\Category $record, Actions\DeleteAction $action) {
                    if ($record->children()->exists()) {
                        \Filament\Notifications\Notification::make()
                            ->title('Không thể xóa')
                            ->body("Danh mục \"{$record->name}\" đang có " . $record->children()->count() . " danh mục con. Hãy xóa hoặc chuyển các danh mục con trước.")
                            ->danger()
                            ->send();
                        $action->halt();
                    }
                }),
        ];
    }
}
