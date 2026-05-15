<?php

namespace App\Filament\Imports;

use App\Models\Author;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Filament\Notifications\Notification;
use Illuminate\Support\Number;
use Illuminate\Validation\Rule;

class AuthorImporter extends Importer
{
    protected static ?string $model = Author::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('name')
                ->label('Tên tác giả')
                ->requiredMapping()
                ->rules(['required', 'max:255'])
                ->exampleHeader('name')
                ->examples(['Nguyễn Nhật Ánh']),
            ImportColumn::make('email')
                ->label('Email')
                ->rules(['nullable', 'email', 'max:255', Rule::unique('authors', 'email')])
                ->exampleHeader('email')
                ->examples(['nguyennhatanh@example.com']),
        ];
    }

    public function resolveRecord(): Author
    {
        return new Author;
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Nhập tác giả thành công: '.Number::format($import->successful_rows).' dòng.';

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
