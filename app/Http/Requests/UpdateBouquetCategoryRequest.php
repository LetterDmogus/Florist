<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBouquetCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('bouquets.manage');
    }

    public function rules(): array
    {
        $categoryId = $this->route('bouquet_category') instanceof \App\Models\BouquetCategory 
            ? $this->route('bouquet_category')->id 
            : $this->route('bouquet_category');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'required', 'string', 'max:255', 'unique:bouquet_categories,slug,' . $categoryId],
        ];
    }
}
