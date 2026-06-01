<?php

use App\Services\Ai\MeilisearchRagIndexConfigurator;
use Illuminate\Support\Facades\Artisan;

beforeEach(function (): void {
    config([
        'ai.rag.index_name' => 'books',
        'ai.rag.embedder_name' => 'gemini_embedding_2_768',
        'ai.rag.embedding_dimensions' => 768,
    ]);
});

test('ai meilisearch configure normalizes searchable attributes validates then updates embedder', function (): void {
    $configurator = Mockery::mock(MeilisearchRagIndexConfigurator::class);
    $configurator->shouldReceive('embedderPayload')
        ->once()
        ->andReturn([
            'gemini_embedding_2_768' => [
                'source' => 'userProvided',
                'dimensions' => 768,
            ],
        ]);
    $configurator->shouldReceive('catalogSearchableAttributes')
        ->once()
        ->andReturn(['name', 'author_names', 'description']);
    $configurator->shouldReceive('ensureCatalogSearchableAttributes')
        ->once()
        ->with('books')
        ->andReturn(false);
    $configurator->shouldReceive('assertRagEmbeddingTextNotSearchable')
        ->once()
        ->with('books')
        ->ordered();
    $configurator->shouldReceive('configure')
        ->once()
        ->with('books')
        ->ordered();

    $this->app->instance(MeilisearchRagIndexConfigurator::class, $configurator);

    Artisan::call('ai:meilisearch-configure');

    $output = Artisan::output();

    expect($output)->toContain('Meilisearch embedder [gemini_embedding_2_768]')
        ->and($output)->not->toContain('searchableAttributes normalized');
});

test('ai meilisearch configure reports when searchable attributes were normalized', function (): void {
    $configurator = Mockery::mock(MeilisearchRagIndexConfigurator::class);
    $configurator->shouldReceive('embedderPayload')->once();
    $configurator->shouldReceive('ensureCatalogSearchableAttributes')
        ->once()
        ->with('books')
        ->andReturn(true);
    $configurator->shouldReceive('assertRagEmbeddingTextNotSearchable')->once()->with('books');
    $configurator->shouldReceive('configure')->once()->with('books');
    $configurator->shouldReceive('catalogSearchableAttributes')
        ->once()
        ->andReturn(['name', 'author_names', 'description']);

    $this->app->instance(MeilisearchRagIndexConfigurator::class, $configurator);

    Artisan::call('ai:meilisearch-configure');

    expect(Artisan::output())->toContain('searchableAttributes normalized to catalog-only fields');
});

test('ai meilisearch configure dry run prints payload without calling Meilisearch', function (): void {
    $configurator = Mockery::mock(MeilisearchRagIndexConfigurator::class);
    $configurator->shouldReceive('embedderPayload')
        ->once()
        ->andReturn([
            'gemini_embedding_2_768' => [
                'source' => 'userProvided',
                'dimensions' => 768,
            ],
        ]);
    $configurator->shouldReceive('catalogSearchableAttributes')
        ->once()
        ->andReturn(['name', 'author_names', 'description']);
    $configurator->shouldNotReceive('ensureCatalogSearchableAttributes');
    $configurator->shouldNotReceive('configure');
    $configurator->shouldNotReceive('assertRagEmbeddingTextNotSearchable');

    $this->app->instance(MeilisearchRagIndexConfigurator::class, $configurator);

    Artisan::call('ai:meilisearch-configure', ['--dry-run' => true]);

    $output = Artisan::output();

    expect($output)->toContain('Dry run')
        ->and($output)->toContain('userProvided')
        ->and($output)->toContain('author_names');
});

test('ai meilisearch configure fails before embedder update when searchable attributes remain unsafe', function (): void {
    $configurator = Mockery::mock(MeilisearchRagIndexConfigurator::class);
    $configurator->shouldReceive('embedderPayload')->once();
    $configurator->shouldReceive('catalogSearchableAttributes')->once();
    $configurator->shouldReceive('ensureCatalogSearchableAttributes')
        ->once()
        ->with('books')
        ->andReturn(false);
    $configurator->shouldReceive('assertRagEmbeddingTextNotSearchable')
        ->once()
        ->with('books')
        ->andThrow(new \RuntimeException("searchableAttributes must not use wildcard '*' for index books; rag_embedding_text would be keyword-searchable."));
    $configurator->shouldNotReceive('configure');

    $this->app->instance(MeilisearchRagIndexConfigurator::class, $configurator);

    $exitCode = Artisan::call('ai:meilisearch-configure');

    expect($exitCode)->toBe(1)
        ->and(Artisan::output())->toContain("wildcard '*'");
});

test('ai meilisearch configure fails when rag_embedding_text is searchable', function (): void {
    $configurator = Mockery::mock(MeilisearchRagIndexConfigurator::class);
    $configurator->shouldReceive('embedderPayload')->once();
    $configurator->shouldReceive('catalogSearchableAttributes')->once();
    $configurator->shouldReceive('ensureCatalogSearchableAttributes')->once()->with('books');
    $configurator->shouldReceive('assertRagEmbeddingTextNotSearchable')
        ->once()
        ->with('books')
        ->andThrow(new \RuntimeException('rag_embedding_text must not be listed in searchableAttributes for index books.'));
    $configurator->shouldNotReceive('configure');

    $this->app->instance(MeilisearchRagIndexConfigurator::class, $configurator);

    $exitCode = Artisan::call('ai:meilisearch-configure');

    expect($exitCode)->toBe(1)
        ->and(Artisan::output())->toContain('rag_embedding_text must not be listed');
});

test('ai meilisearch configure fails when Meilisearch configure throws', function (): void {
    $configurator = Mockery::mock(MeilisearchRagIndexConfigurator::class);
    $configurator->shouldReceive('embedderPayload')->once();
    $configurator->shouldReceive('catalogSearchableAttributes')->once();
    $configurator->shouldReceive('ensureCatalogSearchableAttributes')->once();
    $configurator->shouldReceive('assertRagEmbeddingTextNotSearchable')->once();
    $configurator->shouldReceive('configure')
        ->once()
        ->andThrow(new \RuntimeException('Connection refused'));

    $this->app->instance(MeilisearchRagIndexConfigurator::class, $configurator);

    $exitCode = Artisan::call('ai:meilisearch-configure');

    expect($exitCode)->toBe(1)
        ->and(Artisan::output())->toContain('Connection refused');
});
