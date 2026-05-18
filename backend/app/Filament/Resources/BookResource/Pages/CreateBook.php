<?php

namespace App\Filament\Resources\BookResource\Pages;

use App\Filament\Concerns\HandlesBooksPriceCheckConstraintViolation;
use App\Filament\Resources\BookResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;

class CreateBook extends CreateRecord
{
    use HandlesBooksPriceCheckConstraintViolation;

    protected static string $resource = BookResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            return parent::handleRecordCreation($data);
        } catch (QueryException $e) {
            $this->abortOnBooksPricesCheckConstraint($e);
        }
    }

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
