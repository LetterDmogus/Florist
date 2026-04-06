<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportItemUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('inventory.manage');
    }

    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', 'exists:item_categories,id'],
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
        ];
    }
}
