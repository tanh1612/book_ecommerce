<?php

namespace App\Services\Ai\Support;

use App\Services\Ai\Dto\RetrievedBookPromptContext;

class BookMentionMatcher
{
    public function normalizeForMatching(string $text): string
    {
        $normalized = VietnameseAccentFolder::fold(mb_strtolower(trim($text)));

        return preg_replace('/\s+/u', ' ', $normalized) ?? '';
    }

    /**
     * @param  list<RetrievedBookPromptContext>  $bookContexts
     * @return list<RetrievedBookPromptContext>
     */
    public function filterMentionedInOrder(string $matchingText, array $bookContexts): array
    {
        $mentioned = [];

        foreach ($bookContexts as $context) {
            $position = $this->findEarliestMentionPosition($matchingText, $context);
            if ($position !== null) {
                $mentioned[] = ['context' => $context, 'position' => $position];
            }
        }

        if ($mentioned === []) {
            return [];
        }

        usort($mentioned, static fn (array $left, array $right): int => $left['position'] <=> $right['position']);

        return array_map(static fn (array $item): RetrievedBookPromptContext => $item['context'], $mentioned);
    }

    public function isMentioned(string $matchingText, RetrievedBookPromptContext $context): bool
    {
        return $this->findEarliestMentionPosition($matchingText, $context) !== null;
    }

    public function findEarliestMentionPosition(string $matchingText, RetrievedBookPromptContext $context): ?int
    {
        $foldedName = $this->normalizeForMatching($context->name);

        if ($foldedName !== '') {
            $position = mb_strpos($matchingText, $foldedName);
            if ($position !== false) {
                return $position;
            }
        }

        $compactName = $this->compactFoldedName($foldedName);

        if ($compactName !== '' && mb_strlen($compactName) >= 6) {
            $position = mb_strpos($matchingText, $compactName);
            if ($position !== false) {
                return $position;
            }
        }

        return $this->findEarliestSignificantTokenPosition($matchingText, $foldedName);
    }

    private function findEarliestSignificantTokenPosition(string $matchingText, string $foldedName): ?int
    {
        $tokens = array_values(array_filter(
            preg_split('/\s+/u', $foldedName) ?: [],
            static fn (string $token): bool => mb_strlen($token) >= 4,
        ));

        if ($tokens === []) {
            return null;
        }

        $earliest = null;

        foreach ($tokens as $token) {
            if (! str_contains($matchingText, $token)) {
                return null;
            }

            $position = mb_strpos($matchingText, $token);
            if ($position === false) {
                return null;
            }

            $earliest = $earliest === null ? $position : min($earliest, $position);
        }

        if (count($tokens) === 1 && mb_strlen($tokens[0]) < 6) {
            return null;
        }

        if (count($tokens) > 1) {
            $matchedCount = 0;
            foreach ($tokens as $token) {
                if (str_contains($matchingText, $token)) {
                    $matchedCount++;
                }
            }

            if ($matchedCount < 2) {
                return null;
            }
        }

        return $earliest;
    }

    private function compactFoldedName(string $foldedName): string
    {
        $parts = preg_split('/\s*,\s*/u', $foldedName) ?: [];

        if (count($parts) > 1) {
            return trim((string) end($parts));
        }

        $tokens = preg_split('/\s+/u', trim($foldedName)) ?: [];

        if (count($tokens) <= 3) {
            return $foldedName;
        }

        return implode(' ', array_slice($tokens, -3));
    }
}
