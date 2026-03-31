<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasAnyRole(['super-admin', 'admin']);
    }

    public function rules(): array
    {
        return [
            'item_id' => ['required', 'integer', 'exists:item_units,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'price_at_the_time' => ['required', 'numeric', 'min:0'],
            'total' => ['required', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'type' => ['required', Rule::in(['in', 'out', 'damaged', 'sold'])],
        ];
    }
}
