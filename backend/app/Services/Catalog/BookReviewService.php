<?php

namespace App\Services\Catalog;

use App\Enums\Review\ReviewStatus;
use App\Models\Book;
use App\Models\Review;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class BookReviewService
{
    public function paginateApprovedReviewsBySlug(string $slug, int $perPage = 10): LengthAwarePaginator
    {
        $slug = trim($slug);
        if ($slug === '') {
            abort(404);
        }

        $book = Book::query()->where('slug', $slug)->firstOrFail();

        return Review::query()
            ->where('book_id', $book->id)
            ->where('status', ReviewStatus::APPROVED)
            ->with([
                'account.profile:account_id,first_name,last_name',
            ])
            ->orderByDesc('rating')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }
}
