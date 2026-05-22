<?php

namespace App\Http\Resources;

use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property Review $resource
 */
class ReviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Review $review */
        $review = $this->resource;

        return [
            'id' => $review->id,
            'rating' => (float) $review->rating,
            'comment' => $review->comment,
            'created_at' => $review->created_at?->toIso8601String(),
            'reviewer_name' => $this->reviewerName($review),
            'status' => $this->when(
                $request->is('api/v1/account/*'),
                $review->status?->value,
            ),
        ];
    }

    private function reviewerName(Review $review): ?string
    {
        if (! $review->relationLoaded('account') || $review->account === null) {
            return null;
        }

        $profile = $review->account->relationLoaded('profile')
            ? $review->account->profile
            : null;

        $fullName = $profile?->full_name ?? '';

        if ($fullName !== '') {
            return $fullName;
        }

        return 'Khách hàng';
    }
}
