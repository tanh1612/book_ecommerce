<?php

namespace App\Services\Search;

use App\Models\Book;
use App\Models\Inventory;

class BookSearchDocumentFactory
{
    /**
     * @return array<string, mixed>
     */
    public function make(Book $book): array
    {
        $book->loadMissing([
            'authors:id,name',
            'categories:id',
            'detail:book_id,description',
            'inventories:id,book_id,quantity,reserved_quantity',
        ]);

        $availableStock = (int) $book->inventories->sum(
            fn (Inventory $inventory): int => max(
                0,
                (int) $inventory->quantity - (int) $inventory->reserved_quantity,
            ),
        );

        return [
            'id' => (int) $book->id,
            'name' => (string) $book->name,
            'slug' => (string) $book->slug,
            'description' => (string) ($book->detail?->description ?? ''),
            'author_names' => $book->authors->pluck('name')->values()->all(),
            'author_ids' => $book->authors->pluck('id')->map(fn (mixed $id): int => (int) $id)->values()->all(),
            'category_ids' => $book->categories->pluck('id')->map(fn (mixed $id): int => (int) $id)->values()->all(),
            'publisher_id' => $book->publisher_id === null ? null : (int) $book->publisher_id,
            'supplier_id' => $book->supplier_id === null ? null : (int) $book->supplier_id,
            'selling_price' => (float) $book->selling_price,
            'average_rating' => (float) $book->average_rating,
            'review_count' => (int) $book->review_count,
            'available_stock' => $availableStock,
            'in_stock' => $availableStock > 0,
            'is_active' => (bool) $book->is_active,
            'created_at' => $book->created_at?->timestamp,
        ];
    }
}
