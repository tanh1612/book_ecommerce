<?php

namespace App\Filament\Imports;

use App\Models\Supplier;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Filament\Notifications\Notification;
use Illuminate\Support\Number;
use Illuminate\Validation\Rule;

class SupplierImporter extends Importer
{
    protected static ?string $model = Supplier::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('name')
                ->label('Tên nhà cung cấp')
                ->requiredMapping()
                ->rules(['required', 'max:255', 'unique:suppliers,name'])
                ->exampleHeader('name')
                ->examples(['Fahasa']),
            ImportColumn::make('email')
                ->label('Email')
                ->rules(['nullable', 'email', 'max:255', Rule::unique('suppliers', 'email')])
                ->exampleHeader('email')
                ->examples(['info@fahasa.com']),
        ];
    }

    public function resolveRecord(): Supplier
    {
        return new Supplier;
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Nhập nhà cung cấp thành công: '.Number::format($import->successful_rows).' dòng.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' dòng bị lỗi.';
        }

        return $body;
    }

    public static function modifyCompletedNotification(Notification $notification, Import $import): Notification
    {
        if ($import->getFailedRowsCount() > 0) {
            $notification->color('warning');
        } else {
            $notification->color('success');
        }

        return $notification;
    }
}
