<?php

namespace App\Http\Requests\Recommendation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TrackBookViewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'source' => [
                'nullable',
                'string',
                'max:30',
                Rule::in(['book_detail', 'home', 'catalog']),
            ],
        ];
    }
}
