<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('orders.edit');
    }

    public function rules(): array
    {
        return [
            'customer_mode' => ['required', Rule::in(['existing', 'new'])],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'new_customer_name' => ['nullable', 'string', 'max:255'],
            'new_customer_phone_number' => ['nullable', 'string', 'max:20'],
            'shipping_date' => ['required', 'date'],
            'shipping_time' => ['required', 'date_format:H:i'],
            'shipping_type' => ['required', Rule::in(['delivery', 'pickup'])],
            'shipping_fee' => ['nullable', 'numeric', 'min:0'],
            'delivery_mode' => ['nullable', Rule::in(['existing', 'new'])],
            'delivery_id' => ['nullable', 'integer', Rule::exists('deliveries', 'id')->whereNull('deleted_at')],
            'delivery_recipient_name' => ['nullable', 'string', 'max:255'],
            'delivery_recipient_phone' => ['nullable', 'string', 'max:20'],
            'delivery_full_address' => ['nullable', 'string'],
            'down_payment' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string'],
            'payment_status' => ['required', Rule::in(['unpaid', 'dp', 'paid'])],
            'order_status' => ['required', Rule::in(Order::ORDER_STATUSES)],

            // Order details
            'details' => ['required', 'array', 'min:1'],
            'details.*.id' => ['nullable', 'integer'], // ID detail lama jika ada
            'details.*.item_type' => ['required', Rule::in(['bouquet', 'inventory_item'])],
            'details.*.mode' => ['nullable', Rule::in(['catalog', 'custom'])],
            'details.*.quantity' => ['nullable', 'integer', 'min:1'],
            'details.*.bouquet_unit_id' => ['nullable', 'integer', Rule::exists('bouquet_units', 'id')->whereNull('deleted_at')],
            'details.*.inventory_item_id' => ['nullable', 'integer', Rule::exists('item_units', 'id')->whereNull('deleted_at')],
            'details.*.money_bouquet' => ['nullable', 'numeric', 'min:0'],
            'details.*.greeting_card' => ['nullable', 'string'],
            'details.*.sender_name' => ['nullable', 'string', 'max:255'],
            'details.*.custom_category_id' => ['nullable', 'integer', Rule::exists('bouquet_categories', 'id')->whereNull('deleted_at')],
            'details.*.custom_name' => ['nullable', 'string', 'max:255'],
            'details.*.custom_serial_number' => ['nullable', 'string', 'max:255'],
            'details.*.custom_price' => ['nullable', 'numeric', 'min:0'],
            'details.*.custom_note' => ['nullable', 'string'],
            'details.*.money_amount' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $customerMode = $this->input('customer_mode', 'existing');
            if ($customerMode === 'new') {
                if (! $this->filled('new_customer_name')) {
                    $validator->errors()->add('new_customer_name', 'Nama customer baru wajib diisi.');
                }
                if (! $this->filled('new_customer_phone_number')) {
                    $validator->errors()->add('new_customer_phone_number', 'Nomor telepon customer baru wajib diisi.');
                }
            } elseif (! $this->filled('customer_id')) {
                $validator->errors()->add('customer_id', 'Customer wajib dipilih.');
            }

            if ($this->input('shipping_type') === 'delivery') {
                if ($this->input('delivery_mode') === 'existing') {
                    if (! $this->filled('delivery_id')) {
                        $validator->errors()->add('delivery_id', 'Delivery terdaftar wajib dipilih.');
                    }
                } else {
                    if (! $this->filled('delivery_recipient_name')) {
                        $validator->errors()->add('delivery_recipient_name', 'Nama penerima wajib diisi untuk delivery.');
                    }
                    if (! $this->filled('delivery_recipient_phone')) {
                        $validator->errors()->add('delivery_recipient_phone', 'Nomor telepon penerima wajib diisi untuk delivery.');
                    }
                    if (! $this->filled('delivery_full_address')) {
                        $validator->errors()->add('delivery_full_address', 'Alamat lengkap wajib diisi untuk delivery.');
                    }
                }
            }

            $details = $this->input('details', []);
            foreach ($details as $index => $detail) {
                $itemType = $detail['item_type'] ?? null;
                $mode = $detail['mode'] ?? 'catalog';

                if ($itemType === 'bouquet') {
                    if ($mode === 'custom') {
                        // Hanya wajibkan kolom custom jika ini adalah item BARU (tidak ada id)
                        if (empty($detail['id'])) {
                            if (empty($detail['custom_category_id'])) {
                                $validator->errors()->add("details.{$index}.custom_category_id", 'Kategori custom bouquet wajib dipilih.');
                            }
                            if (empty($detail['custom_name'])) {
                                $validator->errors()->add("details.{$index}.custom_name", 'Nama custom bouquet wajib diisi.');
                            }
                            if (! isset($detail['custom_price']) || $detail['custom_price'] === '') {
                                $validator->errors()->add("details.{$index}.custom_price", 'Harga custom bouquet wajib diisi.');
                            }
                        }
                    } elseif (empty($detail['bouquet_unit_id'])) {
                        $validator->errors()->add("details.{$index}.bouquet_unit_id", 'Bouquet wajib dipilih untuk mode katalog.');
                    }
                } elseif ($itemType === 'inventory_item') {
                    if (empty($detail['inventory_item_id'])) {
                        $validator->errors()->add("details.{$index}.inventory_item_id", 'Item inventory wajib dipilih.');
                    }
                    if (empty($detail['quantity'])) {
                        $validator->errors()->add("details.{$index}.quantity", 'Quantity wajib diisi untuk item inventory.');
                    }
                }
            }
        });
    }
}
