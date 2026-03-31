<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\ReportEntry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReportEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasAnyRole(['super-admin', 'admin']);
    }

    public function rules(): array
    {
        return [
            'occurred_on' => ['required', 'date'],
            'category' => ['required', Rule::in(ReportEntry::CATEGORIES)],
            'description' => ['required', 'string', 'max:255'],
            'amount_idr' => ['nullable', 'numeric'],
            'amount_rmb' => ['nullable', 'numeric', 'min:0'],
            'exchange_rate' => ['nullable', 'numeric', 'min:0'],
            'freight_idr' => ['nullable', 'numeric'],
            'tracking_number' => ['nullable', 'string', 'max:100'],
            'code' => ['nullable', 'string', 'max:100'],
            'estimated_arrived_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
