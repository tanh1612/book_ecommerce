<?php

namespace App\Observers;

use App\Models\Author;
use App\Services\Catalog\CatalogCacheService;

class AuthorObserver
{
    public function __construct(
        private CatalogCacheService $catalogCache,
    ) {}

    public function saved(Author $author): void
    {
        $this->catalogCache->forgetBooksForAuthor((int) $author->id);
    }
}
