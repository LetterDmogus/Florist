<?php

declare(strict_types=1);

namespace App\Actions;

use App\Http\Requests\StoreOrderRequest;
use App\Models\BouquetCategory;
use App\Models\BouquetType;
use App\Models\BouquetUnit;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\ItemUnit;
use App\Models\Order;
use App\Models\OrderDetail;
use Illuminate\Support\Facades\DB;

class CreateOrderAction
{
    public function handle(StoreOrderRequest $request): Order
    {
        return DB::transaction(function () use ($request): Order {
            $validated = $request->validated();
            $customerId = $this->resolveCustomerId($validated);
            $resolvedDetails = collect($validated['details'])
                ->map(fn (array $detail): array => $this->resolveDetail($request, $detail))
                ->all();

            // Hitung total dari detail
            $total = collect($resolvedDetails)->sum(fn (array $detail): float => $this->calculateSubtotal($detail));

            /** @var Order $order */
            $order = Order::create([
                'user_id' => $request->user()->id,
                'customer_id' => $customerId,
                'total' => $total,
                'shipping_date' => $validated['shipping_date'],
                'shipping_time' => $validated['shipping_time'],
                'shipping_type' => $validated['shipping_type'],
                'down_payment' => $validated['down_payment'] ?? null,
                'payment_status' => 'unpaid',
                'order_status' => 'pending',
                'description' => $validated['description'] ?? null,
            ]);

            // Buat order details
            foreach ($resolvedDetails as $index => $detail) {
                $subtotal = $this->calculateSubtotal($detail);

                $orderDetail = OrderDetail::create([
                    'order_id' => $order->id,
                    'item_type' => $detail['item_type'],
                    'quantity' => $this->resolveQuantity($detail),
                    'subtotal' => $subtotal,
                    'bouquet_unit_id' => $detail['bouquet_unit_id'] ?? null,
                    'inventory_item_id' => $detail['inventory_item_id'] ?? null,
                    'money_bouquet' => $detail['money_amount'] ?? $detail['money_bouquet'] ?? null,
                    'greeting_card' => $detail['greeting_card'] ?? null,
                    'sender_name' => $detail['sender_name'] ?? null,
                ]);

                // Handle image upload for custom bouquet unit
                if (($detail['mode'] ?? null) === 'custom' && $request->hasFile("details.{$index}.custom_image")) {
                    $customUnit = BouquetUnit::find($detail['bouquet_unit_id']);
                    if ($customUnit) {
                        $customUnit->addMediaFromRequest("details.{$index}.custom_image")
                            ->toMediaCollection('images');
                    }
                }
            }

            $this->storeDeliveryIfNeeded($order, $validated);

            activity('orders')
                ->causedBy($request->user())
                ->performedOn($order)
                ->event('created')
                ->withProperties([
                    'customer_id' => $customerId,
                    'shipping_type' => $validated['shipping_type'],
                    'shipping_date' => $validated['shipping_date'],
                    'shipping_time' => $validated['shipping_time'],
                    'details_count' => count($resolvedDetails),
                    'total' => (float) $total,
                    'down_payment' => (float) ($validated['down_payment'] ?? 0),
                ])
                ->log('order.created');

            return $order->load(['orderDetails', 'customer']);
        });
    }

    private function storeDeliveryIfNeeded(Order $order, array $validated): void
    {
        if (($validated['shipping_type'] ?? 'pickup') !== 'delivery') {
            return;
        }

        $deliveryPayload = $this->resolveDeliveryPayload($validated);

        $order->delivery()->create($deliveryPayload);
    }

    private function resolveDeliveryPayload(array $validated): array
    {
        if (($validated['delivery_mode'] ?? 'new') === 'existing' && ! empty($validated['delivery_id'])) {
            $delivery = Delivery::query()->findOrFail((int) $validated['delivery_id']);

            return [
                'recipient_name' => $delivery->recipient_name,
                'recipient_phone' => $delivery->recipient_phone,
                'full_address' => $delivery->full_address,
            ];
        }

        return [
            'recipient_name' => trim((string) ($validated['delivery_recipient_name'] ?? '')),
            'recipient_phone' => trim((string) ($validated['delivery_recipient_phone'] ?? '')),
            'full_address' => trim((string) ($validated['delivery_full_address'] ?? '')),
        ];
    }

    private function calculateSubtotal(array $detail): float
    {
        $quantity = $this->resolveQuantity($detail);

        if ($detail['item_type'] === 'bouquet') {
            $unit = BouquetUnit::findOrFail($detail['bouquet_unit_id']);
            $price = $detail['money_bouquet'] ?? $unit->price;

            return (float) $price * $quantity;
        }

        $unit = ItemUnit::findOrFail($detail['inventory_item_id']);

        return (float) $unit->price * $quantity;
    }

    private function resolveDetail(StoreOrderRequest $request, array $detail): array
    {
        if (($detail['item_type'] ?? null) !== 'bouquet') {
            $detail['quantity'] = $this->resolveQuantity($detail);

            return $detail;
        }

        $detail['quantity'] = 1;

        if (($detail['mode'] ?? 'catalog') !== 'custom') {
            if (! isset($detail['money_bouquet']) || $detail['money_bouquet'] === null) {
                $detail['money_bouquet'] = (float) BouquetUnit::query()
                    ->findOrFail($detail['bouquet_unit_id'])
                    ->price;
            }

            return $detail;
        }

        if (! $request->user()->can('input custom bouquet')) {
            abort(403, 'Anda tidak memiliki izin untuk membuat custom bouquet.');
        }

        $customUnit = $this->createCustomBouquetUnit($detail);
        $detail['bouquet_unit_id'] = $customUnit->id;
        $detail['money_bouquet'] = $detail['custom_price'];

        return $detail;
    }

    private function createCustomBouquetUnit(array $detail): BouquetUnit
    {
        $category = BouquetCategory::query()->findOrFail($detail['custom_category_id']);

        $customType = BouquetType::query()
            ->where('category_id', $category->id)
            ->where('is_custom', true)
            ->orderBy('id')
            ->first();

        if (! $customType) {
            $customType = BouquetType::create([
                'category_id' => $category->id,
                'name' => 'Custom',
                'description' => 'Tipe custom auto-generated untuk kategori '.$category->name,
                'is_custom' => true,
            ]);
        }

        return BouquetUnit::create([
            'type_id' => $customType->id,
            'serial_number' => $this->generateCustomSerialNumber(),
            'name' => trim((string) $detail['custom_name']),
            'description' => $detail['custom_note'] ?? null,
            'price' => $detail['custom_price'],
        ]);
    }

    private function generateCustomSerialNumber(): string
    {
        do {
            $serial = 'CUST-'.now()->format('YmdHis').'-'.random_int(1000, 9999);
        } while (BouquetUnit::query()->where('serial_number', $serial)->exists());

        return $serial;
    }

    private function resolveQuantity(array $detail): int
    {
        $quantity = (int) ($detail['quantity'] ?? 1);

        return max(1, $quantity);
    }

    private function resolveCustomerId(array $validated): int
    {
        if (($validated['customer_mode'] ?? 'existing') !== 'new') {
            return (int) $validated['customer_id'];
        }

        $customer = Customer::create([
            'name' => trim((string) $validated['new_customer_name']),
            'phone_number' => trim((string) $validated['new_customer_phone_number']),
            'aliases' => [],
        ]);

        return $customer->id;
    }
}
