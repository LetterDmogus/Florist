<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasAnyRole(['super-admin', 'admin']);
    }

    public function rules(): array
    {
        return [
            'shipping_date' => ['sometimes', 'required', 'date'],
            'shipping_time' => ['sometimes', 'required', 'date_format:H:i'],
            'shipping_type' => ['sometimes', 'required', Rule::in(['delivery', 'pickup'])],
            'down_payment' => ['nullable', 'numeric', 'min:0'],
            'payment_status' => ['sometimes', 'required', Rule::in(['unpaid', 'dp', 'paid'])],
            'order_status' => ['sometimes', 'required', Rule::in(Order::ORDER_STATUSES)],
            'description' => ['nullable', 'string'],
        ];
    }
}
