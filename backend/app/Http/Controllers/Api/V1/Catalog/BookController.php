<?php

namespace App\Http\Controllers\Api\V1\Catalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\ListBooksRequest;
use App\Http\Resources\BookDetailResource;
use App\Http\Resources\BookFilterResource;
use App\Http\Resources\BookSummaryResource;
use App\Services\Catalog\BookCatalogService;
use App\Services\Catalog\BookFilterService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BookController extends Controller
{
    public function index(ListBooksRequest $request, BookCatalogService $bookCatalogService): AnonymousResourceCollection
    {
        $paginator = $bookCatalogService->paginateBooks($request->validated());

        return BookSummaryResource::collection($paginator);
    }

    public function show(string $slug, BookCatalogService $bookCatalogService): BookDetailResource
    {
        $book = $bookCatalogService->getBookBySlug($slug);

        return new BookDetailResource($book);
    }

    public function filters(BookFilterService $bookFilterService): BookFilterResource
    {
        return new BookFilterResource($bookFilterService->getMetadata());
    }
}
