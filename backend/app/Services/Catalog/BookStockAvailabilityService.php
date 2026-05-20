<?php

namespace App\Services\Catalog;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class BookStockAvailabilityService
{
    public function __construct(
        private CatalogCacheService $catalogCache,
    ) {}

    /**
     * @return array{available_stock: int, in_stock: bool}
     */
    public function getAvailability(int $bookId): array
    {
        if ($bookId <= 0) {
            return [
                'available_stock' => 0,
                'in_stock' => false,
            ];
        }

        try {
            return $this->catalogCache->rememberBookStock($bookId, fn (): array => $this->resolveAvailabilityFromDatabase($bookId));
        } catch (Throwable $e) {
            Log::warning('Book stock availability cache read failed', [
                'book_id' => $bookId,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            return $this->resolveAvailabilityFromDatabase($bookId);
        }
    }

    /**
     * @return array{available_stock: int, in_stock: bool}
     */
    private function resolveAvailabilityFromDatabase(int $bookId): array
    {
        $available = (int) DB::table('inventories')
            ->where('book_id', $bookId)
            ->selectRaw('COALESCE(SUM(GREATEST(quantity - reserved_quantity, 0)), 0) as available')
            ->value('available');

        return [
            'available_stock' => $available,
            'in_stock' => $available > 0,
        ];
    }
}
