<?php

namespace App\Filament\Imports;

use App\Models\Book;
use App\Models\Inventory;
use App\Services\Inventory\InventoryRestockService;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Filament\Notifications\Notification;
use Illuminate\Support\Number;
use Illuminate\Validation\Rule;

class InventoryImporter extends Importer
{
    protected static ?string $model = Inventory::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('book_sku')
                ->label('SKU sách')
                ->requiredMapping()
                ->rules(['required', 'max:255', Rule::exists('books', 'sku')])
                ->exampleHeader('book_sku')
                ->examples(['8936071673916']),

            ImportColumn::make('warehouse_id')
                ->label('ID kho')
                ->requiredMapping()
                ->numeric()
                ->rules([
                    'required',
                    'integer',
                    Rule::exists('warehouses', 'id')->where('is_active', true),
                ])
                ->exampleHeader('warehouse_id')
                ->examples(['1']),

            ImportColumn::make('quantity')
                ->label('Số lượng nhập')
                ->requiredMapping()
                ->numeric()
                ->rules(['required', 'integer', 'min:0'])
                ->exampleHeader('quantity')
                ->examples(['50']),

            ImportColumn::make('location_code')
                ->label('Mã vị trí')
                ->requiredMapping()
                ->rules(['required', 'string', 'max:50'])
                ->exampleHeader('location_code')
                ->examples(['A01-03']),
        ];
    }

    public function resolveRecord(): Inventory
    {
        return new Inventory;
    }

    public function fillRecord(): void
    {
        // Inventory import writes through InventoryRestockService to preserve restock rules.
    }

    protected function beforeValidate(): void
    {
        $this->data['book_sku'] = trim((string) ($this->data['book_sku'] ?? ''));
        $this->data['location_code'] = trim((string) ($this->data['location_code'] ?? ''));
    }

    public function saveRecord(): void
    {
        $bookId = Book::query()
            ->where('sku', $this->data['book_sku'])
            ->value('id');

        if ($bookId === null) {
            throw new RowImportFailedException("Không tìm thấy sách có SKU '{$this->data['book_sku']}'.");
        }

        app(InventoryRestockService::class)->createOrRestock([
            'book_id' => (int) $bookId,
            'warehouse_id' => (int) $this->data['warehouse_id'],
            'quantity' => (int) $this->data['quantity'],
            'location_code' => $this->data['location_code'],
            'last_restocked_at' => now(),
            'sold_quantity' => 0,
            'reserved_quantity' => 0,
        ]);
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Nhập tồn kho thành công: '.Number::format($import->successful_rows).' dòng.';

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
