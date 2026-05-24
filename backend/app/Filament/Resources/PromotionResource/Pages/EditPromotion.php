<?php

namespace App\Filament\Resources\PromotionResource\Pages;

use App\Enums\Promotion\PromotionStatus;
use App\Filament\Resources\PromotionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPromotion extends EditRecord
{
    protected static string $resource = PromotionResource::class;

    protected function authorizeAccess(): void
    {
        $this->record->refresh();

        abort_unless(static::getResource()::canEdit($this->getRecord()), 403);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['status']);

        $data['status'] = PromotionStatus::SCHEDULED->value;

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
