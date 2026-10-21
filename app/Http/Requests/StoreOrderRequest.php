<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'variant_id' => ['required', 'integer'],
            'payment_method' => ['required', 'string', 'max:30'],
            'target_user_id' => ['nullable', 'string', 'max:50'],
            'target_server_id' => ['nullable', 'string', 'max:20'],
            'promo_code' => ['nullable', 'string', 'max:30'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
        ];
    }
}
