<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportBouquetUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('bouquets.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'mimes:xlsx,xls,csv',
                'max:10240', // Max 10MB
            ],
            'type_id' => [
                'nullable',
                'integer',
                'exists:bouquet_types,id',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'File Excel bouquet wajib diunggah.',
            'file.file' => 'File yang diunggah tidak valid.',
            'file.mimes' => 'Format file harus berupa Excel (.xlsx, .xls) atau .csv.',
            'file.max' => 'Ukuran file tidak boleh lebih dari 10MB.',
            'type_id.exists' => 'Tipe bouquet yang dipilih tidak valid.',
        ];
    }
}
