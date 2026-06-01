<?php

namespace App\Services\Ai;

use App\Services\Ai\Dto\ChatEvaluationResult;
use App\Services\Ai\Dto\RetrievedBookPromptContext;
use App\Services\Ai\Support\VietnameseAccentFolder;

class ChatEvaluationService
{
    private const GROUNDEDNESS_PASS = 0.7;

    private const GROUNDEDNESS_WARNING = 0.4;

    private const RELEVANCE_MATCHED_BASE = 0.7;

    private const RELEVANCE_NO_CONTEXT_MATCH = 0.85;

    private const RELEVANCE_ENTITY_BOOST = 0.15;

    private const RELEVANCE_LOW_MATCH = 0.35;

    /**
     * @param  list<RetrievedBookPromptContext>  $bookContexts
     */
    public function evaluate(
        string $question,
        string $answer,
        bool $retrievalMatched,
        array $bookContexts,
    ): ChatEvaluationResult {
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
            $normalizedAnswer,
            $retrievalMatched,
            $bookContexts,
            $hasHallucinationRisk,
        );

        $verdict = $this->determineVerdict(
            $groundednessScore,
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
        foreach ($this->extractPriceCandidates($normalizedAnswer) as $candidate) {
            if (! $this->priceMatchesAnyFact($candidate, $bookContexts)) {
                $riskFlags[] = 'ungrounded_price';
            }
        }

        foreach ($this->extractYearCandidates($normalizedAnswer) as $year) {
            if (! $this->yearMatchesAnyFact($year, $bookContexts)) {
                $riskFlags[] = 'ungrounded_year';
            }
        }

        foreach ($this->extractPageCountCandidates($normalizedAnswer) as $pages) {
            if (! $this->pageCountMatchesAnyFact($pages, $bookContexts)) {
                $riskFlags[] = 'ungrounded_page_count';
            }
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
            if ($normalizedName !== '') {
                $checks++;
                if (str_contains($normalizedAnswer, $normalizedName)) {
                    $hits++;
                }
            }

            foreach ($context->authorNames as $authorName) {
                $normalizedAuthor = $this->normalizeText($authorName);
                if ($normalizedAuthor === '') {
                    continue;
                }

                $checks++;
                if (str_contains($normalizedAnswer, $normalizedAuthor)) {
                    $hits++;
                }
            }
        }

        foreach ($this->extractPriceCandidates($normalizedAnswer) as $candidate) {
            $checks++;
            if ($this->priceMatchesAnyFact($candidate, $bookContexts)) {
                $hits++;
            }
        }

        foreach ($this->extractYearCandidates($normalizedAnswer) as $year) {
            $checks++;
            if ($this->yearMatchesAnyFact($year, $bookContexts)) {
                $hits++;
            }
        }

        foreach ($this->extractPageCountCandidates($normalizedAnswer) as $pages) {
            $checks++;
            if ($this->pageCountMatchesAnyFact($pages, $bookContexts)) {
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
        string $normalizedAnswer,
        bool $retrievalMatched,
        array $bookContexts,
        bool $hasHallucinationRisk,
    ): float {
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
        float $groundednessScore,
        bool $hasHallucinationRisk,
        bool $retrievalMatched,
        array $bookContexts,
        string $normalizedAnswer,
    ): string {
        if (! $retrievalMatched && $this->suggestsSpecificBooksWhenUnmatched($normalizedAnswer, $bookContexts)) {
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
        foreach ($bookContexts as $context) {
            if ($context->publicationYear === $year) {
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
        $normalized = mb_strtolower(trim($text));
        // Accent-insensitive matching for Gemini answers that retain Vietnamese diacritics.
        $normalized = VietnameseAccentFolder::fold($normalized);

        return preg_replace('/\s+/u', ' ', $normalized) ?? '';
    }
}
