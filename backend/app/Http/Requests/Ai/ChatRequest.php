<?php

namespace App\Http\Requests\Ai;

use Illuminate\Foundation\Http\FormRequest;

class ChatRequest extends FormRequest
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
        $min = config('ai.chat.min_question_length');
        $max = config('ai.chat.max_question_length');

        return [
            'session_id' => ['required', 'string', 'uuid:4'],
            'question' => ['required', 'string', "min:{$min}", "max:{$max}"],
        ];
    }
}
