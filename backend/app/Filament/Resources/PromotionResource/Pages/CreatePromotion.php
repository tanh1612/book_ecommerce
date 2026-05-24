<?php

namespace App\Filament\Resources\PromotionResource\Pages;

use App\Enums\Promotion\PromotionStatus;
use App\Filament\Resources\PromotionResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePromotion extends CreateRecord
{
    protected static string $resource = PromotionResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        unset($data['status']);

        $data['status'] = PromotionStatus::SCHEDULED->value;

        return $data;
    }
}
