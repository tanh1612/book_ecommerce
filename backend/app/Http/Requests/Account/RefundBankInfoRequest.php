<?php

namespace App\Http\Requests\Account;

use Illuminate\Foundation\Http\FormRequest;

class RefundBankInfoRequest extends FormRequest
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
            'bank_code' => ['required', 'string', 'regex:/^[A-Z0-9]{2,20}$/'],
            'account_number' => ['required', 'string', 'regex:/^\d{6,19}$/'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('account_number')) {
            $this->merge([
                'account_number' => preg_replace('/\s+/', '', (string) $this->input('account_number')),
            ]);
        }

        if ($this->has('bank_code')) {
            $this->merge([
                'bank_code' => strtoupper(trim((string) $this->input('bank_code'))),
            ]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'bank_code.regex' => 'Mã ngân hàng không hợp lệ.',
            'account_number.regex' => 'Số tài khoản phải gồm 6–19 chữ số.',
        ];
    }
}
