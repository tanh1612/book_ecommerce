<?php

namespace App\Http\Resources\Ai;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read array{message_id: int|null, answer: string, sources: array<int, mixed>} $resource
 */
class ChatMessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'message_id' => $this->resource['message_id'],
            'answer' => $this->resource['answer'],
            'sources' => $this->resource['sources'],
        ];
    }
}
