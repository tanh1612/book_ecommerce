<?php

namespace App\Http\Requests\Account;

use App\Enums\Account\UserGender;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
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
            'first_name' => ['nullable', 'string', 'max:100', 'required_without_all:last_name,phone,gender,birthday'],
            'last_name' => ['nullable', 'string', 'max:100', 'required_without_all:first_name,phone,gender,birthday'],
            'phone' => ['nullable', 'string', 'max:20', 'required_without_all:first_name,last_name,gender,birthday'],
            'gender' => ['nullable', Rule::enum(UserGender::class), 'required_without_all:first_name,last_name,phone,birthday'],
            'birthday' => ['nullable', 'date', 'before_or_equal:today', 'required_without_all:first_name,last_name,phone,gender'],
            'account_id' => ['prohibited'],
            'email' => ['prohibited'],
            'role' => ['prohibited'],
            'is_active' => ['prohibited'],
            'email_verified_at' => ['prohibited'],
        ];
    }
}
