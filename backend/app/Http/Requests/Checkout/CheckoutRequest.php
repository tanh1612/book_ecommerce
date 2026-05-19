<?php

namespace App\Http\Requests\Checkout;

use App\Enums\Order\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class CheckoutRequest extends FormRequest
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
            'idempotency_key' => ['required', 'string', 'uuid'],
            'payment_method' => ['required', new Enum(PaymentMethod::class)],
            'shipping_method_id' => ['required', 'integer', Rule::exists('shipping_methods', 'id')],
            'note' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'address_id' => [
                'nullable',
                'integer',
                Rule::exists('addresses', 'id')->where('account_id', (int) $this->user()->id),
            ],
            'shipping.recipient_name' => ['required_without:address_id', 'string', 'max:100'],
            'shipping.recipient_phone' => ['required_without:address_id', 'string', 'max:20'],
            'shipping.province_code' => ['required_without:address_id', 'string', 'max:20'],
            'shipping.ward_code' => ['required_without:address_id', 'string', 'max:20'],
            'shipping.detail_address' => ['required_without:address_id', 'string', 'max:255'],
            'shipping.district_code' => ['prohibited'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $v): void {
            if (! $this->filled('address_id')) {
                return;
            }

            $shipping = $this->input('shipping');
            if (! is_array($shipping)) {
                return;
            }

            foreach ($shipping as $value) {
                if ($value !== null && $value !== '') {
                    $v->errors()->add('shipping', 'Do not send shipping when address_id is set.');

                    return;
                }
            }
        });
    }
}
