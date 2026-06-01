<?php

namespace App\Services\Ai\Support;

class ReciprocalRankFusionMerger
{
    /**
     * @param  list<list<int>>  $rankedBookIdLists
     * @return array<int, float>
     */
    public function merge(array $rankedBookIdLists, int $k = 60): array
    {
        $scores = [];

        foreach ($rankedBookIdLists as $rankedBookIds) {
            foreach ($rankedBookIds as $index => $bookId) {
                $bookId = (int) $bookId;
                $rank = $index + 1;
                $scores[$bookId] = ($scores[$bookId] ?? 0.0) + (1.0 / ($rank + $k));
            }
        }

        return $scores;
    }
}
