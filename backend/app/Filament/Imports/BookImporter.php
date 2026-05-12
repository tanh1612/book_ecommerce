<?php

namespace App\Filament\Imports;

use App\Models\Author;
use App\Models\Book;
use App\Models\Category;
use App\Models\Publisher;
use App\Models\Supplier;
use App\Traits\GeneratesUniqueSlug;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;

class BookImporter extends Importer
{
    use GeneratesUniqueSlug;

    protected static ?string $model = Book::class;

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
                ->rules(['nullable', 'max:255'])
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
                ->rules(['nullable'])
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
                ->label('Tác giả (Cách nhau dấu |)')
                ->rules(['nullable'])
                ->exampleHeader('authors')
                ->examples(['Nguyễn Văn Huyên | Trần Đại Nghĩa'])
                ->fillRecordUsing(fn () => null),
                
            ImportColumn::make('categories')
                ->label('Danh mục (Breadcrumb cách nhau dấu |)')
                ->rules(['nullable'])
                ->exampleHeader('categories')
                ->examples(['Sách tiếng Việt > Tiểu sử - Hồi ký | Sách tiếng Việt > Khoa Học'])
                ->fillRecordUsing(fn () => null),
                
            ImportColumn::make('description')
                ->label('Mô tả')
                ->rules(['nullable'])
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
                ->rules(['nullable', 'max:255'])
                ->exampleHeader('dimensions')
                ->examples(['20.5 x 14.5 x 0.2 cm'])
                ->fillRecordUsing(fn () => null),
                
            ImportColumn::make('publication_year')
                ->label('Năm xuất bản')
                ->numeric()
                ->rules(['nullable', 'integer', 'min:1000', 'max:' . (date('Y') + 1)])
                ->exampleHeader('publication_year')
                ->examples(['2024'])
                ->fillRecordUsing(fn () => null),
                
            ImportColumn::make('translator')
                ->label('Người dịch')
                ->rules(['nullable', 'max:255'])
                ->exampleHeader('translator')
                ->examples(['Nguyễn Mộng Lan'])
                ->fillRecordUsing(fn () => null),
        ];
    }

    public function resolveRecord(): ?Book
    {
        if (filled($this->data['sku'])) {
            if (Book::where('sku', $this->data['sku'])->exists()) {
                throw new RowImportFailedException("SKU [{$this->data['sku']}] đã tồn tại.");
            }
        }
        
        return new Book();
    }

    protected function beforeSave(): void
    {
        // Slug
        $this->record->slug = $this->generateUniqueSlug($this->data['name'], 'books');

        // Supplier (required)
        $supplier = Supplier::where('name', $this->data['supplier'])->first();
        if (!$supplier) {
            throw new RowImportFailedException("Nhà cung cấp '{$this->data['supplier']}' không tồn tại.");
        }
        $this->record->supplier_id = $supplier->id;

        // Publisher (optional)
        if (filled($this->data['publisher'] ?? null)) {
            $this->record->publisher_id = Publisher::where('name', $this->data['publisher'])->first()?->id;
        }

        // Selling price defaults to original price
        if (blank($this->data['selling_price'] ?? null)) {
            $this->record->selling_price = $this->data['original_price'];
        }
        
        // Active defaults to true
        if (!isset($this->data['is_active'])) {
            $this->record->is_active = true;
        }
    }

    protected function afterSave(): void
    {
        // 1. BookDetail
        $languageInput = mb_strtolower(trim($this->data['language'] ?? ''));
        $language = match (true) {
            in_array($languageInput, ['tiếng anh', 'english', 'en']) => 'en',
            default => 'vi',
        };

        $formatInput = mb_strtolower(trim($this->data['format'] ?? ''));
        $format = match (true) {
            in_array($formatInput, ['bìa cứng', 'hardcover']) => 'Bìa cứng',
            default => 'Bìa mềm',
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

        // 2. Cloudinary Upload -> BookImage
        if (filled($this->data['thumbnail_url'] ?? null)) {
            try {
                $slug = $this->record->slug;
                
                // Split URLs by space or newline to allow multiple images
                $urls = preg_split('/\s+/', trim($this->data['thumbnail_url']));
                
                foreach ($urls as $index => $url) {
                    if (blank($url)) continue;
                    
                    $filename = "{$slug}-" . now()->valueOf() . "-{$index}";
                    
                    // Upload to Cloudinary with explicit folder and public_id
                    // This ensures the file is placed in the correct directory regardless of preset settings
                    $result = \CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary::uploadApi()->upload($url, [
                        'folder' => "books/{$slug}",
                        'public_id' => $filename,
                        'resource_type' => 'image',
                    ]);
                    
                    // Save the full path to the database to match Cloudinary's storage structure
                    $this->record->images()->create([
                        'public_id' => "books/{$slug}/{$filename}.jpg",
                        'sort_order' => $index + 1,
                    ]);
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("Import Image Failed for {$this->record->sku}: " . $e->getMessage());
            }
        }

        // 3. Attach authors by name
        if (filled($this->data['authors'] ?? null)) {
            $names = array_map('trim', explode('|', $this->data['authors']));
            $authorIds = Author::whereIn('name', $names)->pluck('id');
            $this->record->authors()->attach($authorIds);
        }

        // 4. Attach categories by breadcrumb (strict, with trim to handle trailing spaces)
        if (filled($this->data['categories'] ?? null)) {
            $breadcrumbs = array_map('trim', explode('|', $this->data['categories']));
            $categoryIds = [];
            
            $allCategories = Category::with('parent.parent')->get();
            
            foreach ($breadcrumbs as $breadcrumb) {
                $category = $allCategories->first(fn ($c) => trim($c->getBreadcrumb()) === $breadcrumb);
                if (!$category) {
                    throw new RowImportFailedException("Danh mục '{$breadcrumb}' không tồn tại.");
                }
                $categoryIds[] = $category->id;
            }
            $this->record->categories()->attach($categoryIds);
        }
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Nhập sách thành công: ' . Number::format($import->successful_rows) . ' dòng.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' ' . Number::format($failedRowsCount) . ' dòng bị lỗi.';
        }

        return $body;
    }
}
