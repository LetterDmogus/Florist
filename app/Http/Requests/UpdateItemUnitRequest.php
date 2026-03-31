<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\ItemUnit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateItemUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasAnyRole(['super-admin', 'admin']);
    }

    public function rules(): array
    {
        $itemUnit = $this->route('item_unit');
        $itemUnitId = $itemUnit instanceof ItemUnit ? $itemUnit->id : $itemUnit;

        return [
            'category_id' => ['sometimes', 'required', 'integer', 'exists:item_categories,id'],
            'serial_number' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('item_units', 'serial_number')->ignore($itemUnitId)],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'individual' => ['sometimes', 'required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'stock' => ['sometimes', 'required', 'integer', 'min:0'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
        ];
    }
}
