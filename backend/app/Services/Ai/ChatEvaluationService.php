<?php

namespace App\Services\Ai;

use App\Services\Ai\Dto\ChatEvaluationResult;
use App\Services\Ai\Dto\RetrievedBookPromptContext;
use App\Services\Ai\Support\AnswerBookSegmenter;
use App\Services\Ai\Support\BookMentionMatcher;
use App\Services\Ai\Support\VietnameseAccentFolder;

class ChatEvaluationService
{
    public function __construct(
        private readonly BookMentionMatcher $bookMentionMatcher,
        private readonly AnswerBookSegmenter $answerBookSegmenter,
    ) {}

    private const GROUNDEDNESS_PASS = 0.7;

    private const GROUNDEDNESS_WARNING = 0.4;

    private const RELEVANCE_MATCHED_BASE = 0.7;

    private const RELEVANCE_NO_CONTEXT_MATCH = 0.85;

    private const RELEVANCE_ENTITY_BOOST = 0.15;

    private const RELEVANCE_LOW_MATCH = 0.35;

    private const RELEVANCE_INTENT_MISMATCH = 0.25;

    /**
     * @param  list<RetrievedBookPromptContext>  $bookContexts
     */
    public function evaluate(
        string $question,
        string $answer,
        bool $retrievalMatched,
        array $bookContexts,
    ): ChatEvaluationResult {
        $normalizedQuestion = $this->normalizeText($question);
        $normalizedAnswer = $this->normalizeText($answer);
        $riskFlags = [];

        $this->collectUngroundedClaimFlags($normalizedAnswer, $bookContexts, $riskFlags);

        $hasHallucinationRisk = $riskFlags !== [];

        $groundednessScore = $this->calculateGroundedness(
            $normalizedAnswer,
            $bookContexts,
            $retrievalMatched,
            $riskFlags,
        );

        $relevanceScore = $this->calculateRelevance(
            $normalizedQuestion,
            $normalizedAnswer,
            $retrievalMatched,
            $bookContexts,
            $hasHallucinationRisk,
        );

        $verdict = $this->determineVerdict(
            $normalizedQuestion,
            $groundednessScore,
            $relevanceScore,
            $hasHallucinationRisk,
            $retrievalMatched,
            $bookContexts,
            $normalizedAnswer,
        );

        return new ChatEvaluationResult(
            groundednessScore: round($groundednessScore, 3),
            relevanceScore: round($relevanceScore, 3),
            hasHallucinationRisk: $hasHallucinationRisk,
            verdict: $verdict,
            riskFlags: $riskFlags,
        );
    }

    /**
     * @param  list<RetrievedBookPromptContext>  $bookContexts
     * @param  list<string>  $riskFlags
     */
    private function collectUngroundedClaimFlags(
        string $normalizedAnswer,
        array $bookContexts,
        array &$riskFlags,
    ): void {
        $citedCount = count($this->answerBookSegmenter->buildCitationSpans($normalizedAnswer, $bookContexts));

        foreach ($this->extractPriceOccurrences($normalizedAnswer) as $occurrence) {
            if ($this->shouldIgnoreAggregatePriceAtOffset($normalizedAnswer, $occurrence['offset'])) {
                continue;
            }

            $this->validateAttributedPrice(
                $occurrence['amount'],
                $occurrence['offset'],
                $normalizedAnswer,
                $bookContexts,
                $citedCount,
                $riskFlags,
            );
        }

        foreach ($this->extractYearOccurrences($normalizedAnswer) as $occurrence) {
            $this->validateAttributedYear(
                $occurrence['year'],
                $occurrence['offset'],
                $normalizedAnswer,
                $bookContexts,
                $citedCount,
                $riskFlags,
            );
        }

        foreach ($this->extractPageOccurrences($normalizedAnswer) as $occurrence) {
            $this->validateAttributedPageCount(
                $occurrence['pages'],
                $occurrence['offset'],
                $normalizedAnswer,
                $bookContexts,
                $citedCount,
                $riskFlags,
            );
        }

        if ($this->containsPercentageCandidate($normalizedAnswer) && ! $this->percentageMatchesAnyFact($normalizedAnswer, $bookContexts)) {
            $riskFlags[] = 'ungrounded_percentage';
        }

        $riskFlags = array_values(array_unique($riskFlags));
    }

    /**
     * @param  list<RetrievedBookPromptContext>  $bookContexts
     * @param  list<string>  $riskFlags
     */
    private function calculateGroundedness(
        string $normalizedAnswer,
        array $bookContexts,
        bool $retrievalMatched,
        array $riskFlags,
    ): float {
        if (in_array('ungrounded_price', $riskFlags, true)
            || in_array('ungrounded_year', $riskFlags, true)
            || in_array('ungrounded_page_count', $riskFlags, true)) {
            return max(0.0, self::GROUNDEDNESS_WARNING - 0.1);
        }

        if ($bookContexts === []) {
            if (! $retrievalMatched && $this->acknowledgesNoContext($normalizedAnswer)) {
                return 0.8;
            }

            return $retrievalMatched ? 0.6 : 0.5;
        }

        $checks = 0;
        $hits = 0;

        foreach ($bookContexts as $context) {
            $normalizedName = $this->normalizeText($context->name);
            if ($normalizedName !== '' && str_contains($normalizedAnswer, $normalizedName)) {
                $checks++;
                $hits++;
            }
        }

        $citedCount = count($this->answerBookSegmenter->buildCitationSpans($normalizedAnswer, $bookContexts));

        foreach ($this->extractPriceOccurrences($normalizedAnswer) as $occurrence) {
            if ($this->shouldIgnoreAggregatePriceAtOffset($normalizedAnswer, $occurrence['offset'])) {
                continue;
            }

            $checks++;
            if ($this->priceClaimIsGrounded(
                $occurrence['amount'],
                $occurrence['offset'],
                $normalizedAnswer,
                $bookContexts,
                $citedCount,
            )) {
                $hits++;
            }
        }

        foreach ($this->extractYearOccurrences($normalizedAnswer) as $occurrence) {
            $checks++;
            if ($this->yearClaimIsGrounded(
                $occurrence['year'],
                $occurrence['offset'],
                $normalizedAnswer,
                $bookContexts,
                $citedCount,
            )) {
                $hits++;
            }
        }

        foreach ($this->extractPageOccurrences($normalizedAnswer) as $occurrence) {
            $checks++;
            if ($this->pageClaimIsGrounded(
                $occurrence['pages'],
                $occurrence['offset'],
                $normalizedAnswer,
                $bookContexts,
                $citedCount,
            )) {
                $hits++;
            }
        }

        if ($checks === 0) {
            return 0.65;
        }

        return min(1.0, $hits / $checks);
    }

    /**
     * @param  list<RetrievedBookPromptContext>  $bookContexts
     */
    private function calculateRelevance(
        string $normalizedQuestion,
        string $normalizedAnswer,
        bool $retrievalMatched,
        array $bookContexts,
        bool $hasHallucinationRisk,
    ): float {
        if ($this->isBookQualityIntentQuestion($normalizedQuestion)
            && $this->answerOnlyPriceOrStock($normalizedAnswer, $bookContexts)) {
            return self::RELEVANCE_INTENT_MISMATCH;
        }

        if ($retrievalMatched && $normalizedAnswer !== '') {
            $score = self::RELEVANCE_MATCHED_BASE;
            if ($this->containsContextEntity($normalizedAnswer, $bookContexts)) {
                $score = min(1.0, $score + self::RELEVANCE_ENTITY_BOOST);
            }
        } elseif (! $retrievalMatched && $this->acknowledgesNoContext($normalizedAnswer)) {
            $score = self::RELEVANCE_NO_CONTEXT_MATCH;
        } elseif (! $retrievalMatched && $this->suggestsSpecificBooksWhenUnmatched($normalizedAnswer, $bookContexts)) {
            $score = self::RELEVANCE_LOW_MATCH;
        } else {
            $score = 0.55;
        }

        if ($hasHallucinationRisk) {
            $score = min($score, 0.45);
        }

        return $score;
    }

    /**
     * @param  list<RetrievedBookPromptContext>  $bookContexts
     */
    private function determineVerdict(
        string $normalizedQuestion,
        float $groundednessScore,
        float $relevanceScore,
        bool $hasHallucinationRisk,
        bool $retrievalMatched,
        array $bookContexts,
        string $normalizedAnswer,
    ): string {
        if (! $retrievalMatched && $this->suggestsSpecificBooksWhenUnmatched($normalizedAnswer, $bookContexts)) {
            return 'fail';
        }

        if ($this->isBookQualityIntentQuestion($normalizedQuestion)
            && $relevanceScore <= self::RELEVANCE_INTENT_MISMATCH) {
            return 'fail';
        }

        if ($hasHallucinationRisk && $groundednessScore < self::GROUNDEDNESS_WARNING) {
            return 'fail';
        }

        if ($hasHallucinationRisk || ($groundednessScore >= self::GROUNDEDNESS_WARNING && $groundednessScore < self::GROUNDEDNESS_PASS)) {
            return 'warning';
        }

        if (! $hasHallucinationRisk && $groundednessScore >= self::GROUNDEDNESS_PASS) {
            return 'pass';
        }

        return 'warning';
    }

    /**
     * @param  list<RetrievedBookPromptContext>  $bookContexts
     */
    private function containsContextEntity(string $normalizedAnswer, array $bookContexts): bool
    {
        foreach ($bookContexts as $context) {
            $normalizedName = $this->normalizeText($context->name);
            if ($normalizedName !== '' && str_contains($normalizedAnswer, $normalizedName)) {
                return true;
            }

            foreach ($context->authorNames as $authorName) {
                $normalizedAuthor = $this->normalizeText($authorName);
                if ($normalizedAuthor !== '' && str_contains($normalizedAnswer, $normalizedAuthor)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  list<RetrievedBookPromptContext>  $bookContexts
     */
    private function suggestsSpecificBooksWhenUnmatched(string $normalizedAnswer, array $bookContexts): bool
    {
        if ($bookContexts !== []) {
            return $this->containsContextEntity($normalizedAnswer, $bookContexts);
        }

        if ($this->extractPriceCandidates($normalizedAnswer) !== []) {
            return true;
        }

        if (preg_match('/\bnen mua\s+sach\s+[a-z0-9]{2,}\b/u', $normalizedAnswer)) {
            return true;
        }

        return (bool) preg_match('/\b(?:nen mua|goi y|de xuat)\b/u', $normalizedAnswer)
            && (bool) preg_match('/\bsach\b/u', $normalizedAnswer)
            && $this->extractPriceCandidates($normalizedAnswer) !== [];
    }

    private function acknowledgesNoContext(string $normalizedAnswer): bool
    {
        $phrases = [
            'khong tim thay',
            'chua tim thay',
            'du lieu hien co',
            'khong co thong tin phu hop',
        ];

        $configured = $this->normalizeText((string) config('ai.chat.no_context_message', ''));

        if ($configured !== '') {
            $phrases[] = $configured;
        }

        foreach ($phrases as $phrase) {
            if ($phrase !== '' && str_contains($normalizedAnswer, $phrase)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<int>
     */
    private function extractPriceCandidates(string $normalizedAnswer): array
    {
        $moneyText = $this->prepareTextForMoneyParsing($normalizedAnswer);
        $candidates = [];

        if (preg_match_all('/\b(\d{1,3}(?:[.,]\d{3})*|\d+)\s*(k|nghin|ngan)\b/u', $moneyText, $thousandsMatches, PREG_SET_ORDER)) {
            foreach ($thousandsMatches as $match) {
                $normalized = $this->normalizeMoneyCandidate($match[1].' '.$match[2]);
                if ($normalized !== null) {
                    $candidates[] = $normalized;
                }
            }
        }

        if (preg_match_all('/\b(\d{1,3}(?:[.,]\d{3})*|\d+)\s*(?:vnd|dong)\b/u', $moneyText, $currencyMatches)) {
            foreach ($currencyMatches[1] as $raw) {
                $normalized = $this->normalizeMoneyCandidate($raw);
                if ($normalized !== null) {
                    $candidates[] = $normalized;
                }
            }
        }

        if (preg_match_all('/\b(\d{4,9})\s+dong\b/u', $moneyText, $plainAmountMatches)) {
            foreach ($plainAmountMatches[1] as $raw) {
                $normalized = $this->normalizeMoneyCandidate($raw);
                if ($normalized !== null) {
                    $candidates[] = $normalized;
                }
            }
        }

        if (preg_match_all('/\b(\d{1,3}(?:[.,]\d{3})+)\b/u', $moneyText, $groupedMatches)) {
            foreach ($groupedMatches[1] as $raw) {
                $normalized = $this->normalizeMoneyCandidate($raw);
                if ($normalized !== null && $normalized >= 1000) {
                    $candidates[] = $normalized;
                }
            }
        }

        return array_values(array_unique($candidates));
    }

    private function prepareTextForMoneyParsing(string $text): string
    {
        $text = mb_strtolower($text);

        $text = preg_replace('/\bvnđ\b/u', ' vnd ', $text) ?? $text;
        $text = preg_replace('/\bđồng\b/u', ' dong ', $text) ?? $text;
        $text = preg_replace('/(\d[\d.,]*)\s*đ\b/u', '$1 dong', $text) ?? $text;
        $text = preg_replace('/\bnghìn\b/u', ' nghin ', $text) ?? $text;
        $text = preg_replace('/\bngàn\b/u', ' ngan ', $text) ?? $text;

        return preg_replace('/\s+/u', ' ', $text) ?? $text;
    }

    /**
     * @return list<int>
     */
    private function extractYearCandidates(string $normalizedAnswer): array
    {
        if (! preg_match_all('/\b(19\d{2}|20\d{2})\b/u', $normalizedAnswer, $matches)) {
            return [];
        }

        $years = array_map('intval', $matches[1]);

        return array_values(array_unique($years));
    }

    /**
     * @return list<int>
     */
    private function extractPageCountCandidates(string $normalizedAnswer): array
    {
        if (! preg_match_all('/\b(\d{1,4})\s*(?:trang|pages?)\b/u', $normalizedAnswer, $matches)) {
            return [];
        }

        $pages = array_map('intval', $matches[1]);

        return array_values(array_unique($pages));
    }

    private function containsPercentageCandidate(string $normalizedAnswer): bool
    {
        return (bool) preg_match('/\b\d{1,3}\s*%/u', $normalizedAnswer);
    }

    /**
     * @param  list<RetrievedBookPromptContext>  $bookContexts
     */
    private function priceMatchesAnyFact(int $candidateAmount, array $bookContexts): bool
    {
        if ($bookContexts === []) {
            return false;
        }

        foreach ($bookContexts as $context) {
            $factAmount = (int) round($context->sellingPrice);
            if ($factAmount === $candidateAmount) {
                return true;
            }

            if (abs($factAmount - $candidateAmount) <= max(1000, (int) round($factAmount * 0.01))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<RetrievedBookPromptContext>  $bookContexts
     */
    private function yearMatchesAnyFact(int $year, array $bookContexts): bool
    {
        $yearString = (string) $year;

        foreach ($bookContexts as $context) {
            if ($context->publicationYear === $year) {
                return true;
            }

            $normalizedName = $this->normalizeText($context->name);
            if ($normalizedName !== '' && str_contains($normalizedName, $yearString)) {
                return true;
            }
        }

        return false;
    }

    private function isBookQualityIntentQuestion(string $normalizedQuestion): bool
    {
        $patterns = [
            '/\bco\s+hay\s+khong\b/u',
            '/\bhay\s+hay\s+do\b/u',
            '/\bdang\s+doc\s+khong\b/u',
            '/\bnen\s+doc\s+khong\b/u',
            '/\bco\s+nen\s+doc\b/u',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $normalizedQuestion)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<RetrievedBookPromptContext>  $bookContexts
     */
    private function answerOnlyPriceOrStock(string $normalizedAnswer, array $bookContexts): bool
    {
        $hasPrice = $this->extractPriceCandidates($normalizedAnswer) !== [];
        $hasStock = (bool) preg_match('/\b(?:con hang|het hang|ton kho|co hang|khong con hang)\b/u', $normalizedAnswer);

        if (! $hasPrice && ! $hasStock) {
            return false;
        }

        return ! $this->answerAddressesBookQuality($normalizedAnswer, $bookContexts);
    }

    /**
     * @param  list<RetrievedBookPromptContext>  $bookContexts
     */
    private function answerAddressesBookQuality(string $normalizedAnswer, array $bookContexts): bool
    {
        if (preg_match('/\b(?:danh gia|rating|diem trung binh|trung binh|sao)\b/u', $normalizedAnswer)) {
            return true;
        }

        if (preg_match('/\b(?:rat hay|kha hay|xuat sac|dang doc|nen doc|khuyen doc|doc thu vi|noi dung hay)\b/u', $normalizedAnswer)) {
            return true;
        }

        if (preg_match('/\b(?:ly do|vi sao|cam nhan|noi dung|mo ta)\b/u', $normalizedAnswer)) {
            return true;
        }

        foreach ($bookContexts as $context) {
            if ($context->averageRating <= 0) {
                continue;
            }

            $ratingVariants = [
                number_format($context->averageRating, 1, '.', ''),
                number_format($context->averageRating, 1, ',', ''),
            ];

            foreach (array_unique($ratingVariants) as $rating) {
                if ($rating !== '' && preg_match('/\b'.preg_quote($rating, '/').'\b/u', $normalizedAnswer)) {
                    return true;
                }
            }

            $wholeRating = (string) (int) round($context->averageRating);
            if ($wholeRating !== '' && preg_match('/\b'.$wholeRating.'\s*(?:sao|star)\b/u', $normalizedAnswer)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<RetrievedBookPromptContext>  $bookContexts
     */
    private function pageCountMatchesAnyFact(int $pages, array $bookContexts): bool
    {
        foreach ($bookContexts as $context) {
            if ($context->numPages === $pages) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<RetrievedBookPromptContext>  $bookContexts
     */
    private function percentageMatchesAnyFact(string $normalizedAnswer, array $bookContexts): bool
    {
        unset($normalizedAnswer, $bookContexts);

        return false;
    }

    private function normalizeMoneyCandidate(string $raw): ?int
    {
        $value = mb_strtolower(trim($raw));

        $multiplier = 1;
        if (preg_match('/\s*(k|nghin|ngan)$/u', $value)) {
            $multiplier = 1000;
            $value = preg_replace('/\s*(k|nghin|ngan)$/u', '', $value) ?? $value;
        }

        $value = preg_replace('/[^\d]/u', '', $value) ?? '';

        if ($value === '') {
            return null;
        }

        $amount = (int) $value * $multiplier;

        return $amount > 0 ? $amount : null;
    }

    private function normalizeText(string $text): string
    {
        return $this->bookMentionMatcher->normalizeForMatching($text);
    }

    /**
     * @param  list<RetrievedBookPromptContext>  $bookContexts
     * @param  list<string>  $riskFlags
     */
    private function validateAttributedPrice(
        int $amount,
        int $offset,
        string $normalizedAnswer,
        array $bookContexts,
        int $citedCount,
        array &$riskFlags,
    ): void {
        if ($this->priceClaimIsGrounded($amount, $offset, $normalizedAnswer, $bookContexts, $citedCount)) {
            return;
        }

        if ($citedCount >= 2 && $this->answerBookSegmenter->attributeClaimOffset($offset, $normalizedAnswer, $bookContexts) === null) {
            $riskFlags[] = 'unattributed_claim';
        }

        $riskFlags[] = 'ungrounded_price';
    }

    /**
     * @param  list<RetrievedBookPromptContext>  $bookContexts
     * @param  list<string>  $riskFlags
     */
    private function validateAttributedYear(
        int $year,
        int $offset,
        string $normalizedAnswer,
        array $bookContexts,
        int $citedCount,
        array &$riskFlags,
    ): void {
        if ($this->yearClaimIsGrounded($year, $offset, $normalizedAnswer, $bookContexts, $citedCount)) {
            return;
        }

        if ($citedCount >= 2 && $this->answerBookSegmenter->attributeClaimOffset($offset, $normalizedAnswer, $bookContexts) === null) {
            $riskFlags[] = 'unattributed_claim';
        }

        $riskFlags[] = 'ungrounded_year';
    }

    /**
     * @param  list<RetrievedBookPromptContext>  $bookContexts
     * @param  list<string>  $riskFlags
     */
    private function validateAttributedPageCount(
        int $pages,
        int $offset,
        string $normalizedAnswer,
        array $bookContexts,
        int $citedCount,
        array &$riskFlags,
    ): void {
        if ($this->pageClaimIsGrounded($pages, $offset, $normalizedAnswer, $bookContexts, $citedCount)) {
            return;
        }

        if ($citedCount >= 2 && $this->answerBookSegmenter->attributeClaimOffset($offset, $normalizedAnswer, $bookContexts) === null) {
            $riskFlags[] = 'unattributed_claim';
        }

        $riskFlags[] = 'ungrounded_page_count';
    }

    /**
     * @param  list<RetrievedBookPromptContext>  $bookContexts
     */
    private function priceClaimIsGrounded(
        int $amount,
        int $offset,
        string $normalizedAnswer,
        array $bookContexts,
        int $citedCount,
    ): bool {
        if ($citedCount === 0) {
            return $this->priceMatchesAnyFact($amount, $bookContexts);
        }

        $context = $this->answerBookSegmenter->attributeClaimOffset($offset, $normalizedAnswer, $bookContexts);

        if ($context === null) {
            return false;
        }

        return $this->priceMatchesContext($amount, $context);
    }

    /**
     * @param  list<RetrievedBookPromptContext>  $bookContexts
     */
    private function yearClaimIsGrounded(
        int $year,
        int $offset,
        string $normalizedAnswer,
        array $bookContexts,
        int $citedCount,
    ): bool {
        if ($citedCount === 0) {
            return $this->yearMatchesAnyFact($year, $bookContexts);
        }

        $context = $this->answerBookSegmenter->attributeClaimOffset($offset, $normalizedAnswer, $bookContexts);

        if ($context === null) {
            return false;
        }

        return $this->yearMatchesContext($year, $context);
    }

    /**
     * @param  list<RetrievedBookPromptContext>  $bookContexts
     */
    private function pageClaimIsGrounded(
        int $pages,
        int $offset,
        string $normalizedAnswer,
        array $bookContexts,
        int $citedCount,
    ): bool {
        if ($citedCount === 0) {
            return $this->pageCountMatchesAnyFact($pages, $bookContexts);
        }

        $context = $this->answerBookSegmenter->attributeClaimOffset($offset, $normalizedAnswer, $bookContexts);

        if ($context === null) {
            return false;
        }

        return $context->numPages === $pages;
    }

    private function priceMatchesContext(int $candidateAmount, RetrievedBookPromptContext $context): bool
    {
        $factAmount = (int) round($context->sellingPrice);

        if ($factAmount === $candidateAmount) {
            return true;
        }

        return abs($factAmount - $candidateAmount) <= max(1000, (int) round($factAmount * 0.01));
    }

    private function yearMatchesContext(int $year, RetrievedBookPromptContext $context): bool
    {
        if ($context->publicationYear === $year) {
            return true;
        }

        $normalizedName = $this->normalizeText($context->name);

        return $normalizedName !== '' && str_contains($normalizedName, (string) $year);
    }

    private function shouldIgnoreAggregatePriceAtOffset(string $normalizedAnswer, int $offset): bool
    {
        $windowStart = max(0, $offset - 48);
        $windowLength = $offset - $windowStart;

        if ($windowLength <= 0) {
            return false;
        }

        $window = mb_substr($normalizedAnswer, $windowStart, $windowLength);

        return (bool) preg_match('/\b(?:tong|tong cong|ca hai|combo)\b/u', $window);
    }

    /**
     * @return list<array{amount: int, offset: int}>
     */
    private function extractPriceOccurrences(string $normalizedAnswer): array
    {
        $moneyText = $this->prepareTextForMoneyParsing($normalizedAnswer);
        $occurrences = [];

        if (preg_match_all('/\b(\d{1,3}(?:[.,]\d{3})*|\d+)\s*(k|nghin|ngan)\b/u', $moneyText, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($matches as $match) {
                $amount = $this->normalizeMoneyCandidate($match[1][0].' '.$match[2][0]);
                if ($amount !== null) {
                    $occurrences[] = ['amount' => $amount, 'offset' => $this->mapMoneyTextOffsetToNormalized($normalizedAnswer, $moneyText, $match[0][1])];
                }
            }
        }

        if (preg_match_all('/\b(\d{1,3}(?:[.,]\d{3})*|\d+)\s*(?:vnd|dong)\b/u', $moneyText, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($matches as $match) {
                $amount = $this->normalizeMoneyCandidate($match[1][0]);
                if ($amount !== null) {
                    $occurrences[] = ['amount' => $amount, 'offset' => $this->mapMoneyTextOffsetToNormalized($normalizedAnswer, $moneyText, $match[0][1])];
                }
            }
        }

        if (preg_match_all('/\b(\d{4,9})\s+dong\b/u', $moneyText, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($matches as $match) {
                $amount = $this->normalizeMoneyCandidate($match[1][0]);
                if ($amount !== null) {
                    $occurrences[] = ['amount' => $amount, 'offset' => $this->mapMoneyTextOffsetToNormalized($normalizedAnswer, $moneyText, $match[0][1])];
                }
            }
        }

        if (preg_match_all('/\b(\d{1,3}(?:[.,]\d{3})+)\b/u', $moneyText, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($matches as $match) {
                $amount = $this->normalizeMoneyCandidate($match[1][0]);
                if ($amount !== null && $amount >= 1000) {
                    $occurrences[] = ['amount' => $amount, 'offset' => $this->mapMoneyTextOffsetToNormalized($normalizedAnswer, $moneyText, $match[0][1])];
                }
            }
        }

        return $this->uniqueOccurrences($occurrences);
    }

    private function mapMoneyTextOffsetToNormalized(string $normalizedAnswer, string $moneyText, int $moneyOffset): int
    {
        if ($moneyText === $normalizedAnswer) {
            return $moneyOffset;
        }

        $needle = trim(mb_substr($moneyText, $moneyOffset, 24));
        if ($needle === '') {
            return $moneyOffset;
        }

        $mapped = mb_strpos($normalizedAnswer, $needle);

        return $mapped !== false ? $mapped : $moneyOffset;
    }

    /**
     * @return list<array{year: int, offset: int}>
     */
    private function extractYearOccurrences(string $normalizedAnswer): array
    {
        if (! preg_match_all('/\b(19\d{2}|20\d{2})\b/u', $normalizedAnswer, $matches, PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $occurrences = [];

        foreach ($matches[1] as $match) {
            $occurrences[] = [
                'year' => (int) $match[0],
                'offset' => $match[1],
            ];
        }

        return $this->uniqueOccurrences($occurrences);
    }

    /**
     * @return list<array{pages: int, offset: int}>
     */
    private function extractPageOccurrences(string $normalizedAnswer): array
    {
        if (! preg_match_all('/\b(\d{1,4})\s*(?:trang|pages?)\b/u', $normalizedAnswer, $matches, PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $occurrences = [];

        foreach ($matches[0] as $index => $match) {
            $occurrences[] = [
                'pages' => (int) $matches[1][$index][0],
                'offset' => $match[1],
            ];
        }

        return $this->uniqueOccurrences($occurrences);
    }

    /**
     * @template T of array<string, int>
     * @param  list<T>  $occurrences
     * @return list<T>
     */
    private function uniqueOccurrences(array $occurrences): array
    {
        $unique = [];

        foreach ($occurrences as $occurrence) {
            $key = implode(':', $occurrence);
            $unique[$key] = $occurrence;
        }

        return array_values($unique);
    }
}
