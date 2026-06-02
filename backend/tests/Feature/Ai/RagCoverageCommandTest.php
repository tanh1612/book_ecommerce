<?php

use App\Services\Ai\RagVectorCoverageReporter;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('rag coverage command prints coverage metrics', function (): void {
    $mock = Mockery::mock(RagVectorCoverageReporter::class);
    $mock->shouldReceive('report')->once()->andReturn([
        'active_books' => 100,
        'vectorized_books' => 95,
        'missing_vectors' => 5,
        'coverage_pct' => 95.0,
        'index_name' => 'books',
        'embedder_name' => 'gemini_embedding_2_768',
        'embedding_dimensions' => 768,
    ]);
    app()->instance(RagVectorCoverageReporter::class, $mock);

    $this->artisan('ai:rag:coverage')
        ->assertSuccessful()
        ->expectsOutputToContain('active_books: 100')
        ->expectsOutputToContain('vectorized_books: 95')
        ->expectsOutputToContain('missing_vectors: 5')
        ->expectsOutputToContain('coverage_pct: 95.00')
        ->expectsOutputToContain('index_name: books')
        ->expectsOutputToContain('embedder_name: gemini_embedding_2_768')
        ->expectsOutputToContain('embedding_dimensions: 768');
});

test('rag coverage command supports json output', function (): void {
    $mock = Mockery::mock(RagVectorCoverageReporter::class);
    $mock->shouldReceive('report')->once()->andReturn([
        'active_books' => 200,
        'vectorized_books' => 150,
        'missing_vectors' => 50,
        'coverage_pct' => 75.0,
        'index_name' => 'books',
        'embedder_name' => 'gemini_embedding_2_768',
        'embedding_dimensions' => 768,
    ]);
    app()->instance(RagVectorCoverageReporter::class, $mock);

    $this->artisan('ai:rag:coverage --json')
        ->assertSuccessful()
        ->expectsOutputToContain('"active_books"');
});
