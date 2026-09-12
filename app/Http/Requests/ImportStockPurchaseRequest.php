<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportStockPurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('stock.manage') ?? false;
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
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'File Excel pembelian wajib diunggah.',
            'file.file' => 'File yang diunggah tidak valid.',
            'file.mimes' => 'Format file harus berupa Excel (.xlsx, .xls) atau .csv.',
            'file.max' => 'Ukuran file tidak boleh lebih dari 10MB.',
        ];
    }
}
