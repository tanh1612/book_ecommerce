<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property array{can_review: bool, review_target_id: int|null} $resource
 */
class BookReviewEligibilityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'can_review' => (bool) $this->resource['can_review'],
            'review_target_id' => $this->resource['review_target_id'],
        ];
    }
}
