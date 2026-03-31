<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasAnyRole(['super-admin', 'admin', 'kasir']);
    }

    public function rules(): array
    {
        return [
            'recipient_name' => ['sometimes', 'required', 'string', 'max:255'],
            'recipient_phone' => ['sometimes', 'required', 'string', 'max:20'],
            'full_address' => ['sometimes', 'required', 'string'],
        ];
    }
}
