<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\ItemCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateItemCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasAnyRole(['super-admin', 'admin']);
    }

    public function rules(): array
    {
        $itemCategory = $this->route('item_category');
        $itemCategoryId = $itemCategory instanceof ItemCategory ? $itemCategory->id : $itemCategory;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('item_categories', 'slug')->ignore($itemCategoryId)],
        ];
    }
}
