<?php

namespace App\Services\Ai;

use App\Services\Ai\Support\VietnameseAccentFolder;

class FollowUpQueryResolver
{
    /**
     * @param  list<array{book_id: int, name: string, slug: string}>  $lastSources
     * @param  array{book_id: int, name: string, slug: string}|null  $currentSource
     */
    public function resolve(string $question, array $lastSources, ?array $currentSource = null): ?string
    {
        $book = $this->resolveSource($question, $lastSources, $currentSource);

        if ($book === null) {
            return null;
        }

        $foldedQuestion = VietnameseAccentFolder::fold(mb_strtolower(trim($question)));
        $intentTail = $this->extractIntentTail($foldedQuestion);

        return trim($book['name'].($intentTail !== '' ? ' '.$intentTail : ''));
    }

    /**
     * @param  list<array{book_id: int, name: string, slug: string}>  $lastSources
     * @param  array{book_id: int, name: string, slug: string}|null  $currentSource
     * @return array{book_id: int, name: string, slug: string}|null
     */
    public function resolveSource(string $question, array $lastSources, ?array $currentSource = null): ?array
    {
        if ($lastSources === []) {
            return null;
        }

        $foldedQuestion = VietnameseAccentFolder::fold(mb_strtolower(trim($question)));

        if (! $this->isFollowUp($foldedQuestion)) {
            return null;
        }

        if ($currentSource !== null && $this->matchesDemonstrativeReference($foldedQuestion)) {
            return $currentSource;
        }

        $index = $this->resolveSourceIndex($foldedQuestion, count($lastSources));

        if ($index === null || $index >= count($lastSources)) {
            return null;
        }

        return $lastSources[$index] ?? null;
    }

    public function isFollowUpQuestion(string $question): bool
    {
        $foldedQuestion = VietnameseAccentFolder::fold(mb_strtolower(trim($question)));

        return $this->isFollowUp($foldedQuestion);
    }

    private function isFollowUp(string $foldedQuestion): bool
    {
        return $this->matchesOrdinalReference($foldedQuestion)
            || $this->matchesDemonstrativeReference($foldedQuestion);
    }

    private function matchesOrdinalReference(string $foldedQuestion): bool
    {
        return (bool) preg_match(
            '/\b(cuon|sach)\s+(dau\s+tien|thu\s+(nhat|1|hai|2|ba|3|tu|4))\b/u',
            $foldedQuestion,
        );
    }

    private function matchesDemonstrativeReference(string $foldedQuestion): bool
    {
        return (bool) preg_match('/\b(cuon|sach)\s+(do|day|nay)\b/u', $foldedQuestion)
            || (bool) preg_match('/\bno\b/u', $foldedQuestion);
    }

    private function resolveSourceIndex(string $foldedQuestion, int $sourceCount): ?int
    {
        if ($sourceCount <= 0) {
            return null;
        }

        if (preg_match('/\b(cuon|sach)\s+(dau\s+tien|thu\s+(nhat|1))\b/u', $foldedQuestion)) {
            return 0;
        }

        if (preg_match('/\b(cuon|sach)\s+thu\s+(hai|2)\b/u', $foldedQuestion)) {
            return $sourceCount >= 2 ? 1 : null;
        }

        if (preg_match('/\b(cuon|sach)\s+thu\s+(ba|3)\b/u', $foldedQuestion)) {
            return $sourceCount >= 3 ? 2 : null;
        }

        if (preg_match('/\b(cuon|sach)\s+thu\s+(tu|4)\b/u', $foldedQuestion)) {
            return $sourceCount >= 4 ? 3 : null;
        }

        if ($this->matchesDemonstrativeReference($foldedQuestion)) {
            return 0;
        }

        return null;
    }

    private function extractIntentTail(string $foldedQuestion): string
    {
        if ($this->asksForBookQuality($foldedQuestion)) {
            return 'co hay khong';
        }

        $phrases = [
            'con hang khong',
            'mua duoc khong',
            'bao nhieu',
            'con hang',
            'con khong',
            'ton kho',
            'het hang',
            'gia',
        ];

        foreach ($phrases as $phrase) {
            if (str_contains($foldedQuestion, $phrase)) {
                return $phrase;
            }
        }

        if (preg_match('/\bcon\s+(cuon|sach)\s+thu\s+(nhat|1|hai|2|ba|3|tu|4)\b/u', $foldedQuestion)) {
            return 'con hang khong';
        }

        return '';
    }

    private function asksForBookQuality(string $foldedQuestion): bool
    {
        return str_contains($foldedQuestion, 'co hay khong')
            || str_contains($foldedQuestion, 'hay hay do')
            || str_contains($foldedQuestion, 'hay khong')
            || str_contains($foldedQuestion, 'dang doc khong')
            || str_contains($foldedQuestion, 'nen doc khong')
            || str_contains($foldedQuestion, 'co nen doc');
    }
}
