<?php

use App\Services\Ai\MeilisearchRagIndexConfigurator;

uses(Tests\TestCase::class);

test('catalogSearchableAttributes reads scout index settings for books', function (): void {
    config([
        'scout.meilisearch.index-settings' => [
            \App\Models\Book::class => [
                'searchableAttributes' => ['name', 'author_names', 'description'],
            ],
        ],
    ]);

    expect(app(MeilisearchRagIndexConfigurator::class)->catalogSearchableAttributes())
        ->toBe(['name', 'author_names', 'description']);
});

test('catalogSearchableAttributes falls back to default catalog fields', function (): void {
    config([
        'scout.meilisearch.index-settings' => [],
    ]);

    expect(app(MeilisearchRagIndexConfigurator::class)->catalogSearchableAttributes())
        ->toBe(['name', 'author_names', 'description']);
});
