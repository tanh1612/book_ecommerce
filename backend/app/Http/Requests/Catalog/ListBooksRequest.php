<?php

namespace App\Http\Requests\Catalog;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ListBooksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'sort' => $this->input('sort', 'newest'),
            'per_page' => $this->input('per_page', 40),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:40'],
            'category' => ['nullable', 'string', 'max:255', Rule::exists('categories', 'slug')->where('is_active', true)],
            'price_min' => ['nullable', 'numeric', 'min:0'],
            'price_max' => ['nullable', 'numeric', 'min:0'],
            'publisher' => ['nullable', 'integer', Rule::exists('publishers', 'id')],
            'supplier' => ['nullable', 'integer', Rule::exists('suppliers', 'id')],
            'sort' => ['required', 'string', Rule::in(['newest', 'price_asc', 'price_desc', 'rating_desc'])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->filled('price_min') && $this->filled('price_max')) {
                $min = (float) $this->input('price_min');
                $max = (float) $this->input('price_max');
                if ($min > $max) {
                    $validator->errors()->add('price_min', 'The price min must be less than or equal to price max.');
                }
            }
        });
    }
}
