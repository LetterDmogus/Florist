<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBouquetUnitRequest extends FormRequest
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
        $bouquetUnit = $this->route('bouquet_unit');
        $bouquetUnitId = is_object($bouquetUnit) ? $bouquetUnit->id : $bouquetUnit;

        return [
            'type_id' => ['sometimes', 'required', 'integer', 'exists:bouquet_types,id'],
            'serial_number' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('bouquet_units', 'serial_number')->ignore($bouquetUnitId)],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'money_bouquet' => ['nullable', 'numeric', 'min:0'],
            'price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
        ];
    }
}
