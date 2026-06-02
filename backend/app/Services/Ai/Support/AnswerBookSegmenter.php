<?php

namespace App\Services\Ai\Support;

use App\Services\Ai\Dto\RetrievedBookPromptContext;

class AnswerBookSegmenter
{
    public function __construct(
        private readonly BookMentionMatcher $bookMentionMatcher,
    ) {}

    /**
     * @param  list<RetrievedBookPromptContext>  $bookContexts
     */
    public function attributeClaimOffset(
        int $offset,
        string $matchingText,
        array $bookContexts,
    ): ?RetrievedBookPromptContext {
        $cited = $this->citedBooksWithPositions($matchingText, $bookContexts);

        if ($cited === []) {
            return null;
        }

        if (count($cited) === 1) {
            return $cited[0]['context'];
        }

        $unit = $this->findUnitAtOffset($matchingText, $offset);
        if ($unit !== null) {
            $booksInUnit = $this->mentionedCitedBooksInText($unit['text'], $cited);
            if (count($booksInUnit) === 1) {
                return $booksInUnit[0];
            }

            if (count($booksInUnit) >= 2) {
                return $this->attributeByNearestMention($offset, $cited);
            }
        }

        if ($offset < $cited[0]['position']) {
            return null;
        }

        foreach ($cited as $index => $entry) {
            $start = $entry['position'];
            $end = $cited[$index + 1]['position'] ?? mb_strlen($matchingText);

            if ($offset >= $start && $offset < $end) {
                return $entry['context'];
            }
        }

        return $this->attributeByNearestMention($offset, $cited);
    }

    /**
     * @param  list<RetrievedBookPromptContext>  $bookContexts
     * @return list<array{context: RetrievedBookPromptContext, position: int, spanText: string}>
     */
    public function buildCitationSpans(string $matchingText, array $bookContexts): array
    {
        $cited = $this->citedBooksWithPositions($matchingText, $bookContexts);

        if ($cited === []) {
            return [];
        }

        if (count($cited) === 1) {
            return [[
                'context' => $cited[0]['context'],
                'position' => $cited[0]['position'],
                'spanText' => $matchingText,
            ]];
        }

        $spans = [];

        foreach ($cited as $index => $entry) {
            $start = $entry['position'];
            $end = $cited[$index + 1]['position'] ?? mb_strlen($matchingText);

            $spans[] = [
                'context' => $entry['context'],
                'position' => $start,
                'spanText' => mb_substr($matchingText, $start, $end - $start),
            ];
        }

        return $spans;
    }

    /**
     * @param  list<RetrievedBookPromptContext>  $bookContexts
     * @return list<array{context: RetrievedBookPromptContext, position: int}>
     */
    private function citedBooksWithPositions(string $matchingText, array $bookContexts): array
    {
        $cited = [];

        foreach ($bookContexts as $context) {
            $position = $this->bookMentionMatcher->findEarliestMentionPosition($matchingText, $context);
            if ($position !== null) {
                $cited[] = ['context' => $context, 'position' => $position];
            }
        }

        usort($cited, static fn (array $left, array $right): int => $left['position'] <=> $right['position']);

        return $cited;
    }

    /**
     * @param  list<array{context: RetrievedBookPromptContext, position: int}>  $cited
     * @return list<RetrievedBookPromptContext>
     */
    private function mentionedCitedBooksInText(string $text, array $cited): array
    {
        $mentioned = [];

        foreach ($cited as $entry) {
            if ($this->bookMentionMatcher->isMentioned($text, $entry['context'])) {
                $mentioned[] = $entry['context'];
            }
        }

        return $mentioned;
    }

    /**
     * @param  list<array{context: RetrievedBookPromptContext, position: int}>  $cited
     */
    private function attributeByNearestMention(int $offset, array $cited): ?RetrievedBookPromptContext
    {
        $nearestAfter = null;
        $nearestAfterDistance = null;
        $nearestBefore = null;
        $nearestBeforeDistance = null;

        foreach ($cited as $entry) {
            if ($entry['position'] >= $offset) {
                $distance = $entry['position'] - $offset;
                if ($nearestAfterDistance === null || $distance < $nearestAfterDistance) {
                    $nearestAfterDistance = $distance;
                    $nearestAfter = $entry['context'];
                }

                continue;
            }

            $distance = $offset - $entry['position'];
            if ($nearestBeforeDistance === null || $distance < $nearestBeforeDistance) {
                $nearestBeforeDistance = $distance;
                $nearestBefore = $entry['context'];
            }
        }

        return $nearestAfter ?? $nearestBefore;
    }

    /**
     * @return array{text: string, start: int, end: int}|null
     */
    private function findUnitAtOffset(string $matchingText, int $offset): ?array
    {
        foreach ($this->splitIntoUnitsWithOffsets($matchingText) as $unit) {
            if ($offset >= $unit['start'] && $offset < $unit['end']) {
                return $unit;
            }
        }

        return null;
    }

    /**
     * @return list<array{text: string, start: int, end: int}>
     */
    private function splitIntoUnitsWithOffsets(string $matchingText): array
    {
        $units = [];
        $length = mb_strlen($matchingText);
        $cursor = 0;

        if ($length === 0) {
            return [];
        }

        $pattern = '/(?<!\d)\.(?!\d)|[!?]|[\n\r]+|•/u';

        while ($cursor < $length) {
            if (preg_match($pattern, $matchingText, $match, PREG_OFFSET_CAPTURE, $cursor)) {
                $delimiterOffset = $match[0][1];
                $unitText = trim(mb_substr($matchingText, $cursor, $delimiterOffset - $cursor));

                if ($unitText !== '') {
                    $units[] = [
                        'text' => $unitText,
                        'start' => $cursor,
                        'end' => $delimiterOffset,
                    ];
                }

                $cursor = $delimiterOffset + mb_strlen($match[0][0]);

                continue;
            }

            $unitText = trim(mb_substr($matchingText, $cursor));

            if ($unitText !== '') {
                $units[] = [
                    'text' => $unitText,
                    'start' => $cursor,
                    'end' => $length,
                ];
            }

            break;
        }

        return $units;
    }
}
