<?php

declare(strict_types=1);

namespace App\Actions;

use App\Http\Requests\StoreOrderRequest;
use App\Models\Order;
use App\Models\OrderDetail;
use Illuminate\Support\Facades\DB;

class CreateOrderAction
{
    public function handle(StoreOrderRequest $request): Order
    {
        return DB::transaction(function () use ($request): Order {
            $validated = $request->validated();

            // Hitung total dari detail
            $total = collect($validated['details'])->sum(function (array $detail): float {
                if ($detail['item_type'] === 'bouquet') {
                    $unit = \App\Models\BouquetUnit::findOrFail($detail['bouquet_unit_id']);
                    return (float) $unit->price * $detail['quantity'];
                }
                $unit = \App\Models\ItemUnit::findOrFail($detail['inventory_item_id']);
                return (float) $unit->price * $detail['quantity'];
            });

            /** @var Order $order */
            $order = Order::create([
                'user_id' => $request->user()->id,
                'customer_id' => $validated['customer_id'],
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
            foreach ($validated['details'] as $detail) {
                $subtotal = $this->calculateSubtotal($detail);

                OrderDetail::create([
                    'order_id' => $order->id,
                    'item_type' => $detail['item_type'],
                    'quantity' => $detail['quantity'],
                    'subtotal' => $subtotal,
                    'bouquet_unit_id' => $detail['bouquet_unit_id'] ?? null,
                    'inventory_item_id' => $detail['inventory_item_id'] ?? null,
                    'money_bouquet' => $detail['money_bouquet'] ?? null,
                    'greeting_card' => $detail['greeting_card'] ?? null,
                    'sender_name' => $detail['sender_name'] ?? null,
                ]);
            }

            return $order->load(['orderDetails', 'customer']);
        });
    }

    private function calculateSubtotal(array $detail): float
    {
        if ($detail['item_type'] === 'bouquet') {
            $unit = \App\Models\BouquetUnit::findOrFail($detail['bouquet_unit_id']);
            return (float) $unit->price * $detail['quantity'];
        }

        $unit = \App\Models\ItemUnit::findOrFail($detail['inventory_item_id']);
        return (float) $unit->price * $detail['quantity'];
    }
}
