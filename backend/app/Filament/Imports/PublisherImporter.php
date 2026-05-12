<?php

namespace App\Filament\Imports;

use App\Models\Publisher;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;

class PublisherImporter extends Importer
{
    protected static ?string $model = Publisher::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('name')
                ->label('Tên NXB')
                ->requiredMapping()
                ->rules(['required', 'max:255', 'unique:publishers,name'])
                ->exampleHeader('name')
                ->examples(['Nhà Xuất Bản Trẻ']),
            ImportColumn::make('email')
                ->label('Email')
                ->rules(['email', 'max:255', 'nullable'])
                ->exampleHeader('email')
                ->examples(['lienhe@nxbtre.com.vn']),
        ];
    }

    public function resolveRecord(): Publisher
    {
        return new Publisher();
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Nhập nhà xuất bản thành công: ' . Number::format($import->successful_rows) . ' dòng.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' ' . Number::format($failedRowsCount) . ' dòng bị lỗi.';
        }

        return $body;
    }
}
