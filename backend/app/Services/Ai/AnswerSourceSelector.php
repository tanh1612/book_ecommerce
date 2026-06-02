<?php

namespace App\Services\Ai;

use App\Services\Ai\Dto\RetrievedBookPromptContext;
use App\Services\Ai\Support\BookMentionMatcher;

class AnswerSourceSelector
{
    public function __construct(
        private readonly BookMentionMatcher $bookMentionMatcher,
    ) {}

    /**
     * @param  list<RetrievedBookPromptContext>  $bookContexts
     * @return list<RetrievedBookPromptContext>
     */
    public function select(string $answer, array $bookContexts, bool $effectiveMatched): array
    {
        if (! $effectiveMatched || $bookContexts === []) {
            return [];
        }

        $matchingText = $this->bookMentionMatcher->normalizeForMatching($answer);

        return $this->bookMentionMatcher->filterMentionedInOrder($matchingText, $bookContexts);
    }
}
