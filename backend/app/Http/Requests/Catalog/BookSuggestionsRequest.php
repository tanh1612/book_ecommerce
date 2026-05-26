<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;

class BookSuggestionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $keyword = $this->input('keyword');

        $this->merge([
            'keyword' => is_string($keyword) ? trim($keyword) : $keyword,
            'limit' => $this->input('limit', 8),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'keyword' => ['required', 'string', 'min:2', 'max:100'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:10'],
        ];
    }
}
