<?php

namespace App\Filament\Imports;

use App\Enums\Book\BookFormat;
use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use App\Models\Publisher;
use App\Models\Supplier;
use App\Traits\GeneratesUniqueSlug;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Number;
use Illuminate\Validation\Rule;

class BookImporter extends Importer
{
    use GeneratesUniqueSlug;

    protected static ?string $model = Book::class;

    /**
     * @var array<string, int>|null
     */
    protected static ?array $categoryBreadcrumbToId = null;

    /** @var list<int> */
    protected array $resolvedAuthorIds = [];

    /** @var list<int> */
    protected array $resolvedCategoryIds = [];

    /** @internal Testing: reset static category map between tests */
    public static function clearBreadcrumbCache(): void
    {
        self::$categoryBreadcrumbToId = null;
    }

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('name')
                ->label('Tên sách')
                ->requiredMapping()
                ->rules(['required', 'max:255'])
                ->exampleHeader('name')
                ->examples(['Danh Nhân Khoa Học Việt Nam']),

            ImportColumn::make('sku')
                ->label('SKU')
                ->rules(['nullable', 'max:255', Rule::unique('books', 'sku')])
                ->exampleHeader('sku')
                ->examples(['8936071673916']),

            ImportColumn::make('original_price')
                ->label('Giá gốc')
                ->requiredMapping()
                ->numeric()
                ->rules(['required', 'numeric', 'min:0'])
                ->exampleHeader('original_price')
                ->examples(['150000']),

            ImportColumn::make('selling_price')
                ->label('Giá bán')
                ->numeric()
                ->rules(['nullable', 'numeric', 'min:0'])
                ->exampleHeader('selling_price')
                ->examples(['120000']),

            ImportColumn::make('thumbnail_url')
                ->label('URL Ảnh')
                ->rules(['nullable', 'string', 'max:5000'])
                ->exampleHeader('thumbnail_url')
                ->examples(['https://cdn.fahasa.com/media/catalog/product/8/9/8936071673916_1_1.jpg'])
                ->fillRecordUsing(fn () => null),

            ImportColumn::make('supplier')
                ->label('Nhà cung cấp (Tên)')
                ->requiredMapping()
                ->rules(['required'])
                ->exampleHeader('supplier')
                ->examples(['Fahasa'])
                ->fillRecordUsing(fn () => null),

            ImportColumn::make('publisher')
                ->label('Nhà xuất bản (Tên)')
                ->rules(['nullable'])
                ->exampleHeader('publisher')
                ->examples(['Nhà Xuất Bản Kim Đồng'])
                ->fillRecordUsing(fn () => null),

            ImportColumn::make('authors')
                ->label('Tác giả (Cách nhau dấu phẩy)')
                ->rules(['nullable'])
                ->exampleHeader('authors')
                ->examples(['Nguyễn Văn Huyên, Trần Đại Nghĩa'])
                ->fillRecordUsing(fn () => null),

            ImportColumn::make('categories')
                ->label('Danh mục (Nhiều breadcrumb cách nhau dấu phẩy)')
                ->rules(['nullable'])
                ->exampleHeader('categories')
                ->examples(['Sách tiếng Việt > Tiểu sử - Hồi ký, Sách tiếng Việt > Khoa Học'])
                ->fillRecordUsing(fn () => null),

            ImportColumn::make('description')
                ->label('Mô tả')
                ->rules(['nullable', 'string', 'max:65535'])
                ->exampleHeader('description')
                ->examples(['<p>Học giả Nguyễn Văn Huyên đã để lại nhiều công trình có giá trị...</p>'])
                ->fillRecordUsing(fn () => null),

            ImportColumn::make('language')
                ->label('Ngôn ngữ')
                ->rules(['nullable', 'max:50'])
                ->exampleHeader('language')
                ->examples(['Tiếng Việt'])
                ->fillRecordUsing(fn () => null),

            ImportColumn::make('format')
                ->label('Hình thức')
                ->rules(['nullable', 'max:100'])
                ->exampleHeader('format')
                ->examples(['Bìa mềm'])
                ->fillRecordUsing(fn () => null),

            ImportColumn::make('num_pages')
                ->label('Số trang')
                ->numeric()
                ->rules(['nullable', 'integer', 'min:1'])
                ->exampleHeader('num_pages')
                ->examples(['32'])
                ->fillRecordUsing(fn () => null),

            ImportColumn::make('weight')
                ->label('Trọng lượng (gr)')
                ->numeric()
                ->rules(['nullable', 'numeric', 'min:0'])
                ->exampleHeader('weight')
                ->examples(['100'])
                ->fillRecordUsing(fn () => null),

            ImportColumn::make('dimensions')
                ->label('Kích thước')
                ->rules(['nullable', 'max:50'])
                ->exampleHeader('dimensions')
                ->examples(['20.5 x 14.5 x 0.2 cm'])
                ->fillRecordUsing(fn () => null),

            ImportColumn::make('publication_year')
                ->label('Năm xuất bản')
                ->numeric()
                ->rules(['nullable', 'integer', 'min:1000', 'max:'.(date('Y') + 1)])
                ->exampleHeader('publication_year')
                ->examples(['2024'])
                ->fillRecordUsing(fn () => null),

            ImportColumn::make('translator')
                ->label('Người dịch')
                ->rules(['nullable', 'max:50'])
                ->exampleHeader('translator')
                ->examples(['Nguyễn Mộng Lan'])
                ->fillRecordUsing(fn () => null),
        ];
    }

    public function resolveRecord(): ?Book
    {
        return new Book;
    }

    protected function beforeSave(): void
    {
        $this->resolvedAuthorIds = [];
        $this->resolvedCategoryIds = [];

        $this->record->slug = $this->generateUniqueSlug($this->data['name'], 'books');

        $supplier = Supplier::where('name', $this->data['supplier'])->first();
        if (! $supplier) {
            throw new RowImportFailedException("Nhà cung cấp '{$this->data['supplier']}' không tồn tại.");
        }
        $this->record->supplier_id = $supplier->id;

        if (filled($this->data['publisher'] ?? null)) {
            $publisher = Publisher::where('name', $this->data['publisher'])->first();
            if (! $publisher) {
                throw new RowImportFailedException("Nhà xuất bản '{$this->data['publisher']}' không tồn tại.");
            }
            $this->record->publisher_id = $publisher->id;
        }

        if (blank($this->data['selling_price'] ?? null)) {
            $this->record->selling_price = $this->data['original_price'];
        }

        if (! isset($this->data['is_active'])) {
            $this->record->is_active = true;
        }

        if (filled($this->data['authors'] ?? null)) {
            $names = array_values(array_unique(array_filter(array_map('trim', explode(',', $this->data['authors'])))));
            foreach ($names as $name) {
                $matches = Author::where('name', $name)->get();
                if ($matches->isEmpty()) {
                    throw new RowImportFailedException("Tác giả '{$name}' không tồn tại.");
                }
                if ($matches->count() > 1) {
                    throw new RowImportFailedException("Tác giả '{$name}' trùng tên trong hệ thống — không thể gán an toàn.");
                }
                $this->resolvedAuthorIds[] = (int) $matches->first()->id;
            }
        }

        if (filled($this->data['categories'] ?? null)) {
            $breadcrumbs = array_values(array_unique(array_filter(array_map('trim', explode(',', $this->data['categories'])))));
            $map = $this->breadcrumbToCategoryIdMap();
            foreach ($breadcrumbs as $breadcrumb) {
                if (! isset($map[$breadcrumb])) {
                    throw new RowImportFailedException("Danh mục '{$breadcrumb}' không tồn tại.");
                }
                $this->resolvedCategoryIds[] = $map[$breadcrumb];
            }
        }
    }

    protected function afterSave(): void
    {
        $languageInput = mb_strtolower(trim($this->data['language'] ?? ''));
        $language = match (true) {
            in_array($languageInput, ['tiếng anh', 'english', 'en'], true) => 'en',
            default => 'vi',
        };

        $formatInput = mb_strtolower(trim($this->data['format'] ?? ''));
        $format = match (true) {
            in_array($formatInput, ['bìa cứng', 'hardcover'], true) => BookFormat::HARDCOVER->value,
            default => BookFormat::PAPERBACK->value,
        };

        $this->record->detail()->create([
            'description' => $this->data['description'] ?? null,
            'language' => $language,
            'format' => $format,
            'num_pages' => $this->data['num_pages'] ?? null,
            'weight' => $this->data['weight'] ?? null,
            'dimensions' => $this->data['dimensions'] ?? null,
            'publication_year' => $this->data['publication_year'] ?? null,
            'translator' => $this->data['translator'] ?? null,
        ]);

        if (filled($this->data['thumbnail_url'] ?? null)) {
            try {
                $slug = $this->record->slug;
                $urls = preg_split('/\s+/', trim((string) $this->data['thumbnail_url']));

                foreach ($urls as $index => $url) {
                    if (blank($url)) {
                        continue;
                    }

                    $filename = "{$slug}-".now()->valueOf()."-{$index}";

                    \CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary::uploadApi()->upload($url, [
                        'folder' => "books/{$slug}",
                        'public_id' => $filename,
                        'resource_type' => 'image',
                    ]);

                    $this->record->images()->create([
                        'public_id' => "books/{$slug}/{$filename}.jpg",
                        'sort_order' => $index + 1,
                    ]);
                }
            } catch (\Throwable $e) {
                Log::error('Book import: Cloudinary upload failed', [
                    'import_id' => $this->import->getKey(),
                    'sku' => $this->record->sku,
                    'book_id' => $this->record->getKey(),
                    'error' => $e->getMessage(),
                    'exception' => $e::class,
                ]);

                throw new RowImportFailedException('Tải ảnh lên Cloudinary thất bại: '.$e->getMessage());
            }
        }

        if ($this->resolvedAuthorIds !== []) {
            $this->record->authors()->attach($this->resolvedAuthorIds);
        }

        if ($this->resolvedCategoryIds !== []) {
            $this->record->categories()->attach($this->resolvedCategoryIds);
        }
    }

    /**
     * @return array<string, int>
     */
    protected function breadcrumbToCategoryIdMap(): array
    {
        if (self::$categoryBreadcrumbToId !== null) {
            return self::$categoryBreadcrumbToId;
        }

        $map = [];
        foreach (Category::with(['parent.parent'])->get() as $category) {
            $map[trim($category->getBreadcrumb())] = $category->id;
        }

        return self::$categoryBreadcrumbToId = $map;
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Nhập sách thành công: '.Number::format($import->successful_rows).' dòng.';

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
