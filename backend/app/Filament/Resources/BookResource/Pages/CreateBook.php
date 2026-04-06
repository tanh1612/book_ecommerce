<?php

namespace App\Filament\Resources\BookResource\Pages;

use App\Filament\Resources\BookResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBook extends CreateRecord
{
    protected static string $resource = BookResource::class;

    protected function afterCreate(): void
    {
        $this->record->detail()->firstOrCreate([
            'book_id' => $this->record->id,
        ], [
            'language' => 'Tiếng Việt',
            'format'   => 'Bìa mềm',
        ]);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
