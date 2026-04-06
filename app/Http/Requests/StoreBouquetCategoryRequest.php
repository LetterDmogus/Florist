<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBouquetCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('bouquets.manage');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'unique:bouquet_categories,slug'],
        ];
    }
}
