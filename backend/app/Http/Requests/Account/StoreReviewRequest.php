<?php

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReviewRequest extends FormRequest
{
    /** @var list<float> */
    private const ALLOWED_RATINGS = [0.5, 1, 1.5, 2, 2.5, 3, 3.5, 4, 4.5, 5];

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('rating')) {
            $this->merge([
                'rating' => round((float) $this->input('rating'), 1),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'rating' => ['required', 'numeric', Rule::in(self::ALLOWED_RATINGS)],
            'comment' => ['nullable', 'string', 'max:2000'],
            'account_id' => ['prohibited'],
            'book_id' => ['prohibited'],
            'order_item_id' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rating.in' => 'Điểm đánh giá phải từ 0.5 đến 5 và chỉ được chọn mức 0.5 (ví dụ: 1, 1.5, 4.5).',
        ];
    }
}
