<?php

use App\Services\Ai\MeilisearchRagDocumentWriter;

uses(Tests\TestCase::class);
use Meilisearch\Client;
use Meilisearch\Contracts\DocumentsQuery;
use Meilisearch\Contracts\DocumentsResults;
use Meilisearch\Endpoints\Indexes;

beforeEach(function (): void {
    config([
        'ai.rag.index_name' => 'books',
        'ai.rag.embedder_name' => 'gemini_embedding_2_768',
    ]);
});

test('get document vectors requests meilisearch with retrieve vectors enabled', function (): void {
    $bookId = 42;
    $vector = [0.1, 0.2, 0.3];

    $results = Mockery::mock(DocumentsResults::class);
    $results->shouldReceive('getResults')->once()->andReturn([
        [
            'id' => $bookId,
            '_vectors' => [
                'gemini_embedding_2_768' => $vector,
            ],
        ],
    ]);

    $index = Mockery::mock(Indexes::class);
    $index->shouldReceive('getDocuments')
        ->once()
        ->with(Mockery::on(function (DocumentsQuery $query) use ($bookId): bool {
            $payload = $query->toArray();

            return filter_var($payload['retrieveVectors'] ?? false, FILTER_VALIDATE_BOOLEAN)
                && (int) ($payload['ids'] ?? 0) === $bookId
                && ($payload['fields'] ?? []) === ['id', '_vectors'];
        }))
        ->andReturn($results);

    $client = Mockery::mock(Client::class);
    $client->shouldReceive('index')->once()->with('books')->andReturn($index);

    $writer = new MeilisearchRagDocumentWriter($client);

    expect($writer->getDocumentVectors($bookId))->toBe($vector);
});

test('get document vectors returns null when embedder vector is missing', function (): void {
    $results = Mockery::mock(DocumentsResults::class);
    $results->shouldReceive('getResults')->once()->andReturn([
        ['id' => 7, '_vectors' => []],
    ]);

    $index = Mockery::mock(Indexes::class);
    $index->shouldReceive('getDocuments')->once()->andReturn($results);

    $client = Mockery::mock(Client::class);
    $client->shouldReceive('index')->once()->with('books')->andReturn($index);

    $writer = new MeilisearchRagDocumentWriter($client);

    expect($writer->getDocumentVectors(7))->toBeNull();
});

test('get document vectors returns null when document is not found', function (): void {
    $results = Mockery::mock(DocumentsResults::class);
    $results->shouldReceive('getResults')->once()->andReturn([]);

    $index = Mockery::mock(Indexes::class);
    $index->shouldReceive('getDocuments')->once()->andReturn($results);

    $client = Mockery::mock(Client::class);
    $client->shouldReceive('index')->once()->with('books')->andReturn($index);

    $writer = new MeilisearchRagDocumentWriter($client);

    expect($writer->getDocumentVectors(999))->toBeNull();
});
