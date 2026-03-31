<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasAnyRole(['super-admin', 'admin', 'manager', 'kasir']);
    }

    public function rules(): array
    {
        $deliveryId = $this->route('delivery')?->id;

        return [
            'order_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('orders', 'id')->whereNull('deleted_at'),
                Rule::unique('deliveries', 'order_id')
                    ->ignore($deliveryId)
                    ->whereNull('deleted_at'),
            ],
            'recipient_name' => ['sometimes', 'required', 'string', 'max:255'],
            'recipient_phone' => ['sometimes', 'required', 'string', 'max:20'],
            'full_address' => ['sometimes', 'required', 'string'],
        ];
    }
}
