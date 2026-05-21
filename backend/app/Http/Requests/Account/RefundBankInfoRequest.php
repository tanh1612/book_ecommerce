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
            'account_holder' => ['required', 'string', 'min:2', 'max:100'],
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

        if ($this->has('account_holder')) {
            $this->merge([
                'account_holder' => trim((string) $this->input('account_holder')),
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
            'account_holder.required' => 'Vui lòng nhập tên chủ tài khoản.',
            'account_holder.min' => 'Tên chủ tài khoản phải có ít nhất 2 ký tự.',
            'account_holder.max' => 'Tên chủ tài khoản không được vượt quá 100 ký tự.',
        ];
    }
}
