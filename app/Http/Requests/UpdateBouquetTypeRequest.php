<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBouquetTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('bouquets.manage');
    }

    public function rules(): array
    {
        return [
            'category_id' => ['sometimes', 'required', 'integer', 'exists:bouquet_categories,id'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_custom' => ['sometimes', 'required', 'boolean'],
        ];
    }
}
