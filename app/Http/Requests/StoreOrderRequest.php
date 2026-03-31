<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasAnyRole(['super-admin', 'admin', 'kasir']);
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'shipping_date' => ['required', 'date'],
            'shipping_time' => ['required', 'date_format:H:i'],
            'shipping_type' => ['required', Rule::in(['delivery', 'pickup'])],
            'down_payment' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],

            // Order details (wajib ada minimal 1)
            'details' => ['required', 'array', 'min:1'],
            'details.*.item_type' => ['required', Rule::in(['bouquet', 'inventory_item'])],
            'details.*.quantity' => ['required', 'integer', 'min:1'],
            'details.*.bouquet_unit_id' => ['nullable', 'integer', 'exists:bouquet_units,id'],
            'details.*.inventory_item_id' => ['nullable', 'integer', 'exists:item_units,id'],
            'details.*.money_bouquet' => ['nullable', 'numeric', 'min:0'],
            'details.*.greeting_card' => ['nullable', 'string'],
            'details.*.sender_name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
