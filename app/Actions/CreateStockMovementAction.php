<?php

declare(strict_types=1);

namespace App\Actions;

use App\Http\Requests\StoreStockMovementRequest;
use App\Models\Customer;
use App\Models\ItemUnit;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateStockMovementAction
{
    public function handle(StoreStockMovementRequest $request): array
    {
        return DB::transaction(function () use ($request): array {
            $validated = $request->validated();

            /** @var ItemUnit $item */
            $item = ItemUnit::query()
                ->lockForUpdate()
                ->findOrFail($validated['item_id']);
            $stockBefore = (int) $item->stock;

            $quantity = (int) $validated['quantity'];
            $type = (string) $validated['type'];

            if (in_array($type, ['out', 'damaged', 'sold'], true) && $item->stock < $quantity) {
                throw ValidationException::withMessages([
                    'quantity' => "Stok {$item->name} tidak mencukupi. Stok tersedia: {$item->stock}.",
                ]);
            }

            $unitPrice = $this->resolveUnitPrice($type, $validated, $item);
            $total = $unitPrice * $quantity;
            $order = null;

            if ($type === 'sold') {
                $order = $this->createOrderFromSale($request, $validated, $item, $quantity, $total);
            }

            $movement = StockMovement::create([
                'item_id' => $item->id,
                'user_id' => $request->user()->id,
                'quantity' => $quantity,
                'price_at_the_time' => $unitPrice,
                'total' => $total,
                'description' => $validated['description'] ?? null,
                'type' => $type,
                'order_id' => $order?->id,
            ]);

            // Update stok berdasarkan tipe gerakan
            match ($type) {
                'in' => $item->increment('stock', $quantity),
                'out', 'damaged', 'sold' => $item->decrement('stock', $quantity),
            };

            $stockAfter = match ($type) {
                'in' => $stockBefore + $quantity,
                default => $stockBefore - $quantity,
            };

            activity('inventory')
                ->causedBy($request->user())
                ->performedOn($movement)
                ->event('stock_movement_created')
                ->withProperties([
                    'item_id' => $item->id,
                    'movement_type' => $type,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total' => $total,
                    'stock_before' => $stockBefore,
                    'stock_after' => $stockAfter,
                    'generated_order_id' => $order?->id,
                ])
                ->log('stock_movement.created');

            return [
                'movement' => $movement->load(['item', 'order.customer']),
                'order' => $order,
            ];
        });
    }

    private function resolveUnitPrice(string $type, array $validated, ItemUnit $item): float
    {
        if ($type === 'in') {
            return (float) ($validated['price_at_the_time'] ?? 0);
        }

        if ($type === 'sold') {
            return (float) $item->price;
        }

        if (isset($validated['price_at_the_time']) && $validated['price_at_the_time'] !== null) {
            return (float) $validated['price_at_the_time'];
        }

        return (float) $item->price;
    }

    private function createOrderFromSale(
        StoreStockMovementRequest $request,
        array $validated,
        ItemUnit $item,
        int $quantity,
        float $total,
    ): Order {
        $customerId = $this->resolveCustomerId($validated);
        $downPayment = (float) ($validated['down_payment'] ?? 0);

        if ($downPayment > $total) {
            throw ValidationException::withMessages([
                'down_payment' => 'Down payment tidak boleh lebih besar dari subtotal item.',
            ]);
        }

        /** @var Order $order */
        $order = Order::create([
            'user_id' => $request->user()->id,
            'customer_id' => $customerId,
            'total' => $total,
            'shipping_date' => $validated['shipping_date'],
            'shipping_time' => $validated['shipping_time'],
            'shipping_type' => $validated['shipping_type'],
            'shipping_fee' => 0,
            'down_payment' => $downPayment > 0 ? $downPayment : null,
            'payment_status' => $this->resolvePaymentStatus($total, $downPayment),
            'order_status' => 'pending',
            'description' => $validated['description'] ?? "Order otomatis dari stok jual item {$item->name}.",
        ]);

        OrderDetail::create([
            'order_id' => $order->id,
            'item_type' => 'inventory_item',
            'quantity' => $quantity,
            'subtotal' => $total,
            'bouquet_unit_id' => null,
            'inventory_item_id' => $item->id,
            'money_bouquet' => null,
            'greeting_card' => null,
            'sender_name' => null,
        ]);

        return $order;
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

    private function resolvePaymentStatus(float $total, float $downPayment): string
    {
        if ($downPayment <= 0) {
            return 'paid';
        }

        if ($downPayment >= $total) {
            return 'paid';
        }

        return 'dp';
    }
}
