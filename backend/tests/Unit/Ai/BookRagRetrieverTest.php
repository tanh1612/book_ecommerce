<?php

use App\Exceptions\Ai\GeminiClientException;
use App\Services\Ai\BookRagRetriever;
use App\Services\Ai\Dto\GeminiEmbeddingResult;
use App\Services\Ai\GeminiClient;
use App\Services\Ai\Support\ReciprocalRankFusionMerger;
use Meilisearch\Client;
use Meilisearch\Endpoints\Indexes;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    config([
        'ai.rag.index_name' => 'books',
        'ai.rag.embedder_name' => 'gemini_embedding_2_768',
        'ai.rag.top_k' => 5,
        'ai.rag.min_score' => 0.80,
        'ai.rag.rrf_min_score' => 0.016,
        'ai.rag.rrf_k' => 60,
        'ai.rag.keyword_top1_min_score' => 0.70,
        'ai.rag.hybrid_semantic_ratio' => 0.6,
    ]);
});

function bookHit(int $id, string $name, float $score, string $slug = 'slug'): array
{
    return [
        'id' => $id,
        'name' => $name,
        'slug' => $slug,
        '_rankingScore' => $score,
    ];
}

function mockRetriever(Client $client, ?GeminiClient $geminiClient = null): BookRagRetriever
{
    $geminiClient ??= Mockery::mock(GeminiClient::class);
    $geminiClient->shouldReceive('embedText')
        ->once()
        ->andReturn(new GeminiEmbeddingResult(
            vector: [0.1, 0.2, 0.3],
            dimensions: 3,
            latencyMs: 12,
            model: 'gemini-embedding-2',
        ));

    return new BookRagRetriever(
        geminiClient: $geminiClient,
        rankFusionMerger: new ReciprocalRankFusionMerger(),
        client: $client,
    );
}

function mockMeilisearchIndex(): array
{
    $index = Mockery::mock(Indexes::class);
    $client = Mockery::mock(Client::class);
    $client->shouldReceive('index')->with('books')->andReturn($index);

    return [$client, $index];
}

test('retrieve returns matched hybrid result when top score meets threshold', function (): void {
    [$client, $index] = mockMeilisearchIndex();
    $index->shouldReceive('search')
        ->once()
        ->with('Dac Nhan Tam', Mockery::on(function (array $options): bool {
            return ($options['filter'] ?? null) === 'is_active = true'
                && ($options['limit'] ?? null) === 5
                && ($options['showRankingScore'] ?? null) === true
                && ($options['hybrid']['semanticRatio'] ?? null) === 0.6
                && ($options['hybrid']['embedder'] ?? null) === 'gemini_embedding_2_768'
                && ($options['vector'] ?? null) === [0.1, 0.2, 0.3];
        }), ['raw' => true])
        ->andReturn([
            'hits' => [bookHit(10, 'Dac Nhan Tam', 0.82, 'dac-nhan-tam')],
        ]);

    $result = mockRetriever($client)->retrieve('Dac Nhan Tam');

    expect($result->matched)->toBeTrue()
        ->and($result->strategy)->toBe('hybrid')
        ->and($result->topScore)->toBe(0.82)
        ->and($result->documents)->toHaveCount(1)
        ->and($result->documents[0]->bookId)->toBe(10)
        ->and($result->documents[0]->slug)->toBe('dac-nhan-tam')
        ->and($result->embeddingLatencyMs)->toBe(12);
});

test('retrieve keeps hybrid strategy and documents when score is below threshold', function (): void {
    [$client, $index] = mockMeilisearchIndex();
    $index->shouldReceive('search')
        ->once()
        ->andReturn([
            'hits' => [bookHit(11, 'Sach khac', 0.40)],
        ]);

    $result = mockRetriever($client)->retrieve('sach ve dien thoai');

    expect($result->matched)->toBeFalse()
        ->and($result->strategy)->toBe('hybrid')
        ->and($result->topScore)->toBe(0.40)
        ->and($result->documents)->toHaveCount(1);
});

test('retrieve returns unmatched hybrid result when Meilisearch returns no hits', function (): void {
    [$client, $index] = mockMeilisearchIndex();
    $index->shouldReceive('search')->once()->andReturn(['hits' => []]);

    $result = mockRetriever($client)->retrieve('Bookify co ban dien thoai khong');

    expect($result->matched)->toBeFalse()
        ->and($result->strategy)->toBe('hybrid')
        ->and($result->topScore)->toBeNull()
        ->and($result->documents)->toBe([]);
});

test('retrieve falls back to rrf when hybrid is unsupported', function (): void {
    [$client, $index] = mockMeilisearchIndex();
    $index->shouldReceive('search')
        ->once()
        ->with('Dac Nhan Tam', Mockery::type('array'), ['raw' => true])
        ->andThrow(new RuntimeException('Unknown field `hybrid` in search parameters'));

    $index->shouldReceive('search')
        ->once()
        ->with('Dac Nhan Tam', Mockery::on(fn (array $options): bool => ! array_key_exists('hybrid', $options)), ['raw' => true])
        ->andReturn([
            'hits' => [bookHit(10, 'Dac Nhan Tam', 0.95, 'dac-nhan-tam')],
        ]);

    $index->shouldReceive('search')
        ->once()
        ->with('', Mockery::on(fn (array $options): bool => ($options['vector'] ?? null) === [0.1, 0.2, 0.3]), ['raw' => true])
        ->andReturn(['hits' => []]);

    $result = mockRetriever($client)->retrieve('Dac Nhan Tam');

    expect($result->strategy)->toBe('rrf')
        ->and($result->matched)->toBeTrue()
        ->and($result->documents[0]->bookId)->toBe(10)
        ->and($result->topScore)->toBeGreaterThanOrEqual(0.016);
});

test('retrieve matches rrf via keyword top1 boost when rrf score is below threshold', function (): void {
    config(['ai.rag.rrf_min_score' => 0.02]);

    [$client, $index] = mockMeilisearchIndex();
    $index->shouldReceive('search')
        ->once()
        ->andThrow(new RuntimeException('invalid hybrid parameter'));

    $index->shouldReceive('search')
        ->once()
        ->with('Dac Nhan Tam', Mockery::type('array'), ['raw' => true])
        ->andReturn([
            'hits' => [bookHit(10, 'Dac Nhan Tam', 0.95, 'dac-nhan-tam')],
        ]);

    $index->shouldReceive('search')
        ->once()
        ->with('', Mockery::type('array'), ['raw' => true])
        ->andReturn(['hits' => []]);

    $result = mockRetriever($client)->retrieve('Dac Nhan Tam');

    expect($result->strategy)->toBe('rrf')
        ->and($result->matched)->toBeTrue()
        ->and($result->topScore)->toBeLessThan(0.02);
});

test('retrieve returns unmatched rrf result when score and keyword boost both fail', function (): void {
    config(['ai.rag.rrf_min_score' => 0.02]);

    [$client, $index] = mockMeilisearchIndex();
    $index->shouldReceive('search')
        ->once()
        ->andThrow(new RuntimeException('Unsupported hybrid search on this Meilisearch version'));

    $index->shouldReceive('search')
        ->once()
        ->with('xyz', Mockery::type('array'), ['raw' => true])
        ->andReturn([
            'hits' => [bookHit(99, 'Khong lien quan', 0.10)],
        ]);

    $index->shouldReceive('search')
        ->once()
        ->with('', Mockery::type('array'), ['raw' => true])
        ->andReturn(['hits' => []]);

    $result = mockRetriever($client)->retrieve('xyz');

    expect($result->strategy)->toBe('rrf')
        ->and($result->matched)->toBeFalse()
        ->and($result->documents)->toHaveCount(1);
});

test('retrieve degrades to strategy none on auth or index errors', function (): void {
    [$client, $index] = mockMeilisearchIndex();
    $index->shouldReceive('search')
        ->once()
        ->andThrow(new RuntimeException('The Authorization header is missing'));

    $result = mockRetriever($client)->retrieve('Dac Nhan Tam');

    expect($result->matched)->toBeFalse()
        ->and($result->strategy)->toBe('none')
        ->and($result->documents)->toBe([]);
});

test('retrieve does not fallback to rrf for unknown field errors unrelated to hybrid', function (): void {
    [$client, $index] = mockMeilisearchIndex();
    $index->shouldReceive('search')
        ->once()
        ->andThrow(new RuntimeException('Unknown field `foo` in search parameters'));

    $result = mockRetriever($client)->retrieve('Dac Nhan Tam');

    expect($result->matched)->toBeFalse()
        ->and($result->strategy)->toBe('none')
        ->and($result->documents)->toBe([]);
});

test('retrieve rethrows embedding failures', function (): void {
    [$client] = mockMeilisearchIndex();

    $geminiClient = Mockery::mock(GeminiClient::class);
    $geminiClient->shouldReceive('embedText')
        ->once()
        ->andThrow(new GeminiClientException(
            message: 'Gemini timeout',
            errorCode: GeminiClientException::TIMEOUT,
            httpStatus: null,
            latencyMs: 15,
        ));

    $retriever = new BookRagRetriever(
        geminiClient: $geminiClient,
        rankFusionMerger: new ReciprocalRankFusionMerger(),
        client: $client,
    );

    expect(fn () => $retriever->retrieve('Dac Nhan Tam'))
        ->toThrow(GeminiClientException::class);
});

test('retrieve degrades to strategy none when rrf fallback also fails', function (): void {
    [$client, $index] = mockMeilisearchIndex();
    $index->shouldReceive('search')
        ->once()
        ->andThrow(new RuntimeException('unknown field hybrid'));

    $index->shouldReceive('search')
        ->once()
        ->with('Dac Nhan Tam', Mockery::type('array'), ['raw' => true])
        ->andThrow(new RuntimeException('Index `books` not found'));

    $result = mockRetriever($client)->retrieve('Dac Nhan Tam');

    expect($result->strategy)->toBe('none')
        ->and($result->matched)->toBeFalse();
});
