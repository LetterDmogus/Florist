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

    protected function prepareForValidation(): void
    {
        if ($this->filled('money_bouquet') && ! $this->filled('price')) {
            $this->merge([
                'price' => $this->input('money_bouquet'),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'type_id' => ['required', 'integer', 'exists:bouquet_types,id'],
            'serial_number' => ['required', 'string', 'max:255', 'unique:bouquet_units,serial_number'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'money_bouquet' => ['nullable', 'numeric', 'min:0'],
            'price' => ['required', 'numeric', 'min:0'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
