<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBouquetUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasAnyRole(['super-admin', 'admin']);
    }

    public function rules(): array
    {
        return [
            'type_id' => ['required', 'integer', 'exists:bouquet_types,id'],
            'serial_number' => ['required', 'string', 'max:255', 'unique:bouquet_units,serial_number'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
        ];
    }
}
