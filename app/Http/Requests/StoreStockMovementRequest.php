<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreStockMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasAnyRole(['super-admin', 'admin']);
    }

    public function rules(): array
    {
        return [
            'movement_type' => ['nullable', Rule::in(['purchase', 'usage', 'sale', 'damaged'])],
            'item_id' => ['required', 'integer', Rule::exists('item_units', 'id')->whereNull('deleted_at')],
            'quantity' => ['required', 'integer', 'min:1'],
            'price_at_the_time' => ['nullable', 'numeric', 'min:0'],
            'total' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'type' => ['required', Rule::in(['in', 'out', 'damaged', 'sold'])],
            'customer_mode' => ['nullable', Rule::in(['existing', 'new'])],
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')->whereNull('deleted_at')],
            'new_customer_name' => ['nullable', 'string', 'max:255'],
            'new_customer_phone_number' => ['nullable', 'string', 'max:20', 'unique:customers,phone_number'],
            'shipping_date' => ['nullable', 'date'],
            'shipping_time' => ['nullable', 'date_format:H:i'],
            'shipping_type' => ['nullable', Rule::in(['delivery', 'pickup'])],
            'down_payment' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $mapping = [
            'purchase' => 'in',
            'usage' => 'out',
            'sale' => 'sold',
            'damaged' => 'damaged',
            'in' => 'in',
            'out' => 'out',
            'sold' => 'sold',
        ];

        $inputType = (string) ($this->input('movement_type') ?? $this->input('type') ?? '');
        $normalizedType = $mapping[$inputType] ?? $inputType;

        $this->merge([
            'type' => $normalizedType,
        ]);

        if ($normalizedType !== 'sold') {
            return;
        }

        if (! $this->filled('customer_mode')) {
            $this->merge([
                'customer_mode' => 'existing',
            ]);
        }

        if (! $this->filled('shipping_date')) {
            $this->merge([
                'shipping_date' => now()->format('Y-m-d'),
            ]);
        }

        if (! $this->filled('shipping_time')) {
            $this->merge([
                'shipping_time' => now()->format('H:i'),
            ]);
        }

        if (! $this->filled('shipping_type')) {
            $this->merge([
                'shipping_type' => 'pickup',
            ]);
        }
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $type = $this->input('type');

            if ($type === 'in' && ! $this->filled('price_at_the_time')) {
                $validator->errors()->add('price_at_the_time', 'Harga modal wajib diisi untuk tipe Pembelian.');
            }

            if ($type !== 'sold') {
                return;
            }

            $customerMode = $this->input('customer_mode', 'existing');
            if ($customerMode === 'new') {
                if (! $this->filled('new_customer_name')) {
                    $validator->errors()->add('new_customer_name', 'Nama customer baru wajib diisi untuk penjualan.');
                }

                if (! $this->filled('new_customer_phone_number')) {
                    $validator->errors()->add('new_customer_phone_number', 'No. HP customer baru wajib diisi untuk penjualan.');
                }

                return;
            }

            if (! $this->filled('customer_id')) {
                $validator->errors()->add('customer_id', 'Customer wajib dipilih untuk penjualan.');
            }
        });
    }
}
