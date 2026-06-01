<?php

namespace App\Http\Requests\Ai;

use App\Enums\Ai\ChatFeedbackRating;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChatFeedbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'session_id' => [
                Rule::requiredIf(fn (): bool => $this->user() === null),
                'nullable',
                'string',
                'uuid:4',
            ],
            'rating' => ['required', Rule::enum(ChatFeedbackRating::class)],
        ];
    }
}
