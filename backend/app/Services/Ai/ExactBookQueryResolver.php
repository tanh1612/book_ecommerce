<?php

namespace App\Services\Ai;

use App\Models\Book;
use App\Services\Ai\Dto\BookRagRetrievedDocument;
use App\Services\Ai\Support\VietnameseAccentFolder;
use Illuminate\Support\Facades\Log;
use Throwable;

class ExactBookQueryResolver
{
    /**
     * Longest phrases first so shorter intent tokens do not strip partial matches.
     *
     * @var list<string>
     */
    private const INTENT_PHRASES = [
        'co ban khong',
        'co ban',
        'ban khong',
        'mua duoc khong',
        'bao nhieu tien',
        'bao nhieu',
        'con hang khong',
        'con hang',
        'con khong',
        'ton kho',
        'het hang',
        'gia ban',
        'gia sach',
        'gia',
    ];

    /**
     * @var list<string>
     */
    private const NOISE_TOKENS = [
        'sach',
        'cuon',
        'quyen',
        'cho',
        'toi',
        'minh',
        'ban',
        'hay',
        'giup',
        'tim',
        'muon',
        'co',
        'khong',
        'vay',
        'the',
        'nhu',
        'nao',
        'duoc',
        'o',
        'la',
        'gi',
        'xin',
        'vui',
        'long',
    ];

    /**
     * @return list<BookRagRetrievedDocument>
     */
    public function resolveToDocuments(string $question): array
    {
        $foldedQuestion = $this->fold($question);

        if (! $this->hasRelevantIntent($foldedQuestion)) {
            return [];
        }

        $searchPhrase = $this->extractSearchPhrase($foldedQuestion);

        if (mb_strlen($searchPhrase) < 4) {
            return [];
        }

        try {
            return $this->findCandidates($searchPhrase);
        } catch (Throwable $e) {
            Log::warning('Exact book query resolver failed', [
                'question' => mb_substr($question, 0, 200),
                'search_phrase' => $searchPhrase,
                'error' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            return [];
        }
    }

    public function requiresExactMatch(string $question): bool
    {
        $foldedQuestion = $this->fold($question);

        if (! $this->hasRelevantIntent($foldedQuestion)) {
            return false;
        }

        if ($this->isTopicAvailabilityQuestion($foldedQuestion)) {
            return false;
        }

        return mb_strlen($this->extractSearchPhrase($foldedQuestion)) >= 4;
    }

    private function hasRelevantIntent(string $foldedQuestion): bool
    {
        foreach (self::INTENT_PHRASES as $phrase) {
            if ($this->containsPhrase($foldedQuestion, $phrase)) {
                return true;
            }
        }

        return false;
    }

    private function isTopicAvailabilityQuestion(string $foldedQuestion): bool
    {
        return (bool) preg_match('/\bsach\s+(?:nao\s+)?ve\b/u', $foldedQuestion);
    }

    private function containsPhrase(string $text, string $phrase): bool
    {
        return (bool) preg_match(
            '/(?<![\p{L}\p{N}])'.preg_quote($phrase, '/').'(?![\p{L}\p{N}])/u',
            $text,
        );
    }

    private function extractSearchPhrase(string $foldedQuestion): string
    {
        $phrase = $foldedQuestion;

        foreach (self::INTENT_PHRASES as $intentPhrase) {
            $phrase = str_replace($intentPhrase, ' ', $phrase);
        }

        foreach (self::NOISE_TOKENS as $noiseToken) {
            $phrase = preg_replace('/\b'.preg_quote($noiseToken, '/').'\b/u', ' ', $phrase) ?? $phrase;
        }

        $phrase = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $phrase) ?? $phrase;

        return trim(preg_replace('/\s+/u', ' ', $phrase) ?? '');
    }

    /**
     * @return list<BookRagRetrievedDocument>
     */
    private function findCandidates(string $searchPhrase): array
    {
        $foldedPhrase = $this->fold($searchPhrase);
        $maxCandidates = max((int) config('ai.chat.exact_book_max_candidates', 3), 1);

        // Include inactive books so exact title + stock questions still resolve after out-of-stock sync.
        $books = Book::query()
            ->select(['id', 'name', 'slug'])
            ->get();

        $scored = [];

        foreach ($books as $book) {
            $foldedName = $this->fold((string) $book->name);
            $foldedSlug = $this->fold(str_replace('-', ' ', (string) $book->slug));
            $priority = $this->matchPriority($foldedPhrase, $foldedName, $foldedSlug);

            if ($priority === null) {
                continue;
            }

            $scored[] = [
                'book' => $book,
                'priority' => $priority,
            ];
        }

        usort($scored, function (array $left, array $right): int {
            $priorityCompare = $left['priority'] <=> $right['priority'];

            if ($priorityCompare !== 0) {
                return $priorityCompare;
            }

            return mb_strlen((string) $right['book']->name) <=> mb_strlen((string) $left['book']->name);
        });

        $documents = [];

        foreach (array_slice($scored, 0, $maxCandidates) as $entry) {
            $book = $entry['book'];
            $priority = (int) $entry['priority'];
            $score = max(1.0 - ($priority * 0.01), 0.9);

            $documents[] = new BookRagRetrievedDocument(
                bookId: (int) $book->id,
                score: $score,
                name: (string) $book->name,
                slug: (string) $book->slug,
                raw: ['source' => 'exact_mysql', 'match_priority' => $priority],
            );
        }

        return $documents;
    }

    private function matchPriority(string $foldedPhrase, string $foldedName, string $foldedSlug): ?int
    {
        if ($foldedPhrase === $foldedName || $foldedPhrase === $foldedSlug) {
            return 0;
        }

        if ($foldedName !== '' && (str_contains($foldedName, $foldedPhrase) || str_contains($foldedPhrase, $foldedName))) {
            return 1;
        }

        if ($foldedSlug !== '' && (str_contains($foldedSlug, $foldedPhrase) || str_contains($foldedPhrase, $foldedSlug))) {
            return 2;
        }

        if ($foldedName !== '') {
            similar_text($foldedPhrase, $foldedName, $percent);

            if ($percent >= 85.0) {
                return 3;
            }
        }

        return null;
    }

    private function fold(string $text): string
    {
        return VietnameseAccentFolder::fold(mb_strtolower(trim($text)));
    }
}
