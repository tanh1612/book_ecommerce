<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property-read array{data: mixed, metadata?: array<string, mixed>|null} $resource
 */
class LocationListResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'success' => true,
            'data' => $this->resource['data'],
            'metadata' => $this->resource['metadata'] ?? null,
        ];
    }
}
