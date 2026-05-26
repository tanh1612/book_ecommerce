<?php

namespace Tests\Support;

use Laravel\Scout\Engines\NullEngine;

class RecordingScoutEngine extends NullEngine
{
    /** @var list<list<array<string, mixed>>> */
    public array $updates = [];

    public function update($models): void
    {
        $this->updates[] = $models
            ->map(fn (mixed $model): array => $model->toSearchableArray())
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function lastDocument(): ?array
    {
        $updates = $this->updates;

        if ($updates === []) {
            return null;
        }

        return $updates[array_key_last($updates)][0] ?? null;
    }
}
