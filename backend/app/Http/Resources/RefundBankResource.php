<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property array{
 *     code: string,
 *     name: string,
 *     short_name: string,
 *     bin: int,
 *     logo: string|null,
 *     lookup_supported: bool,
 *     transfer_supported: bool
 * } $resource
 */
class RefundBankResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->resource['code'],
            'name' => $this->resource['name'],
            'short_name' => $this->resource['short_name'],
            'bin' => $this->resource['bin'],
            'logo' => $this->resource['logo'],
            'lookup_supported' => $this->resource['lookup_supported'],
            'transfer_supported' => $this->resource['transfer_supported'],
        ];
    }
}
