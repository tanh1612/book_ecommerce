<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'role' => $this->role?->value,
            'is_active' => $this->is_active,
            'email_verified_at' => $this->email_verified_at,
            'profile' => $this->whenLoaded('profile', fn (): array => [
                'first_name' => $this->profile?->first_name,
                'last_name' => $this->profile?->last_name,
                'phone' => $this->profile?->phone,
                'gender' => $this->profile?->gender,
                'birthday' => $this->profile?->birthday,
            ]),
        ];
    }
}
