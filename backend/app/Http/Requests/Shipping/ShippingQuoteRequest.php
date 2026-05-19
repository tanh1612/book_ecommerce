<?php

namespace App\Http\Requests\Shipping;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShippingQuoteRequest extends FormRequest
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
            'shipping_method_id' => ['required', 'integer', Rule::exists('shipping_methods', 'id')],
            'address_id' => [
                'nullable',
                'integer',
                Rule::exists('addresses', 'id')->where('account_id', (int) $this->user()->id),
            ],
            'province_code' => ['nullable', 'string', 'max:20'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $v): void {
            $hasAddress = $this->filled('address_id');
            $hasProvince = $this->filled('province_code');

            if ($hasAddress && $hasProvince) {
                $v->errors()->add('address_id', 'Send only address_id or province_code, not both.');
                $v->errors()->add('province_code', 'Send only address_id or province_code, not both.');

                return;
            }

            if (! $hasAddress && ! $hasProvince) {
                $v->errors()->add('address_id', 'Either address_id or province_code is required.');
                $v->errors()->add('province_code', 'Either address_id or province_code is required.');
            }
        });
    }
}
