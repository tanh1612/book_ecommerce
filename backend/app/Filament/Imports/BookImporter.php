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
                ->rules(['required', 'max:255']),
            
            ImportColumn::make('sku')
                ->label('SKU')
                ->rules(['nullable', 'max:255']),
                
            ImportColumn::make('original_price')
                ->label('Giá gốc')
                ->requiredMapping()
                ->numeric()
                ->rules(['required', 'numeric', 'min:0']),
                
            ImportColumn::make('selling_price')
                ->label('Giá bán')
                ->numeric()
                ->rules(['nullable', 'numeric', 'min:0']),
                
            ImportColumn::make('is_active')
                ->label('Trạng thái')
                ->boolean()
                ->rules(['nullable', 'boolean']),
                
            ImportColumn::make('thumbnail_url')
                ->label('URL Ảnh')
                ->rules(['nullable', 'url']),
                
            ImportColumn::make('supplier')
                ->label('Nhà cung cấp (Tên)')
                ->requiredMapping()
                ->rules(['required']),
                
            ImportColumn::make('publisher')
                ->label('Nhà xuất bản (Tên)')
                ->rules(['nullable']),
                
            ImportColumn::make('authors')
                ->label('Tác giả (Cách nhau dấu |)')
                ->rules(['nullable']),
                
            ImportColumn::make('categories')
                ->label('Danh mục (Breadcrumb cách nhau dấu |)')
                ->rules(['nullable']),
                
            ImportColumn::make('description')
                ->label('Mô tả')
                ->rules(['nullable']),
                
            ImportColumn::make('language')
                ->label('Ngôn ngữ')
                ->rules(['nullable', 'max:50']),
                
            ImportColumn::make('format')
                ->label('Hình thức')
                ->rules(['nullable', 'max:100']),
                
            ImportColumn::make('num_pages')
                ->label('Số trang')
                ->numeric()
                ->rules(['nullable', 'integer', 'min:1']),
                
            ImportColumn::make('weight')
                ->label('Trọng lượng (gr)')
                ->numeric()
                ->rules(['nullable', 'numeric', 'min:0']),
                
            ImportColumn::make('dimensions')
                ->label('Kích thước')
                ->rules(['nullable', 'max:255']),
                
            ImportColumn::make('publication_year')
                ->label('Năm xuất bản')
                ->numeric()
                ->rules(['nullable', 'integer', 'min:1000', 'max:' . (date('Y') + 1)]),
                
            ImportColumn::make('translator')
                ->label('Người dịch')
                ->rules(['nullable', 'max:255']),
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
        $this->record->detail()->create([
            'description' => $this->data['description'] ?? null,
            'language' => $this->data['language'] ?? 'vi',
            'format' => $this->data['format'] ?? 'Bìa mềm',
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
                $publicId = "{$slug}-" . now()->valueOf();
                
                $result = Cloudinary::uploadApi()->upload($this->data['thumbnail_url'], [
                    'folder' => "books/{$slug}",
                    'public_id' => $publicId,
                    'resource_type' => 'image',
                ]);
                
                $this->record->images()->create([
                    'image_url' => $result['secure_url'],
                    'public_id' => $result['public_id'],
                    'sort_order' => 1,
                ]);
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

        // 4. Attach categories by breadcrumb (strict)
        if (filled($this->data['categories'] ?? null)) {
            $breadcrumbs = array_map('trim', explode('|', $this->data['categories']));
            $categoryIds = [];
            
            $allCategories = Category::with('parent.parent')->get();
            
            foreach ($breadcrumbs as $breadcrumb) {
                $category = $allCategories->first(fn ($c) => $c->getBreadcrumb() === $breadcrumb);
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
