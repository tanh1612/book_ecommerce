<?php

namespace App\Filament\Imports;

use App\Models\Supplier;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;

class SupplierImporter extends Importer
{
    use \App\Traits\GeneratesUniqueSlug;

    protected static ?string $model = Supplier::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('name')
                ->label('Tên nhà cung cấp')
                ->requiredMapping()
                ->rules(['required', 'max:255', 'unique:suppliers,name']),
            ImportColumn::make('email')
                ->label('Email')
                ->rules(['email', 'max:255', 'nullable']),
        ];
    }

    public function resolveRecord(): Supplier
    {
        return new Supplier();
    }

    protected function beforeSave(): void
    {
        $this->record->slug = $this->generateUniqueSlug($this->data['name'], 'suppliers');
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Nhập nhà cung cấp thành công: ' . Number::format($import->successful_rows) . ' dòng.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' ' . Number::format($failedRowsCount) . ' dòng bị lỗi.';
        }

        return $body;
    }
}
