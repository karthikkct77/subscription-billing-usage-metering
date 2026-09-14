<?php

namespace App\Http\Requests;

use App\Models\Customer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RecordUsageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'merchant_id' => ['required', 'integer', 'exists:merchants,id'],
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'occurred_at' => ['required', 'date'],
            'units' => ['required', 'integer', 'min:1'],
            'idempotency_key' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->has('merchant_id') || $validator->errors()->has('customer_id')) {
                return;
            }

            $merchantId = $this->input('merchant_id');
            $customerId = $this->input('customer_id');

            if ($merchantId && $customerId) {
                $belongsToMerchant = Customer::where('id', $customerId)
                    ->where('merchant_id', $merchantId)
                    ->exists();

                if (! $belongsToMerchant) {
                    $validator->errors()->add(
                        'customer_id',
                        'The selected customer does not belong to the specified merchant.'
                    );
                }
            }
        });
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'units.min' => 'Usage units must be greater than zero.',
        ];
    }
}
