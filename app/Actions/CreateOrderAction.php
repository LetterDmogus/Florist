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
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateOrderAction
{
    public function __construct(
        private readonly SyncOrderShippingExpenseAction $syncOrderShippingExpenseAction,
    ) {}

    public function handle(StoreOrderRequest $request): Order
    {
        $requestId = trim((string) $request->input('request_id', ''));

        try {
            return DB::transaction(function () use ($request): Order {
                $validated = $request->validated();
                $requestId = trim((string) ($validated['request_id'] ?? ''));
                $existingOrder = $this->findExistingOrderByRequestId($requestId);
                if ($existingOrder) {
                    return $existingOrder;
                }

                $customerId = $this->resolveCustomerId($validated);
                $resolvedDetails = collect($validated['details'])
                    ->map(fn (array $detail): array => $this->resolveDetail($request, $detail))
                    ->all();

                // Hitung total dari detail
                $itemsTotal = collect($resolvedDetails)->sum(fn (array $detail): float => $this->calculateSubtotal($detail));
                $shippingFee = $this->resolveShippingFee($validated);
                $total = $itemsTotal + $shippingFee;
                $downPayment = isset($validated['down_payment']) ? (float) $validated['down_payment'] : 0.0;

                if ($downPayment > $itemsTotal) {
                    throw ValidationException::withMessages([
                        'down_payment' => 'Down payment tidak boleh lebih besar dari subtotal item.',
                    ]);
                }

                $paymentStatus = $this->resolvePaymentStatus((float) $total, $downPayment);

                /** @var Order $order */
                $order = Order::create([
                    'user_id' => $request->user()->id,
                    'customer_id' => $customerId,
                    'request_id' => $requestId,
                    'total' => $total,
                    'shipping_date' => $validated['shipping_date'],
                    'shipping_time' => $validated['shipping_time'],
                    'shipping_type' => $validated['shipping_type'],
                    'shipping_fee' => $shippingFee,
                    'down_payment' => $downPayment > 0 ? $downPayment : null,
                    'payment_status' => $paymentStatus,
                    'order_status' => 'pending',
                    'description' => $validated['description'] ?? null,
                ]);

                // Buat order details
                foreach ($resolvedDetails as $index => $detail) {
                    $subtotal = $this->calculateSubtotal($detail);
                    $quantity = $this->resolveQuantity($detail);

                    OrderDetail::create([
                        'order_id' => $order->id,
                        'item_type' => $detail['item_type'],
                        'quantity' => $quantity,
                        'subtotal' => $subtotal,
                        'bouquet_unit_id' => $detail['bouquet_unit_id'] ?? null,
                        'inventory_item_id' => $detail['inventory_item_id'] ?? null,
                        'money_bouquet' => $detail['money_amount'] ?? $detail['money_bouquet'] ?? null,
                        'greeting_card' => $detail['greeting_card'] ?? null,
                        'sender_name' => $detail['sender_name'] ?? null,
                    ]);

                    // Handle stock reduction for inventory items
                    if ($detail['item_type'] === 'inventory_item' && $detail['inventory_item_id']) {
                        $item = ItemUnit::lockForUpdate()->find($detail['inventory_item_id']);
                        if ($item) {
                            $item->decrement('stock', $quantity);

                            \App\Models\StockMovement::create([
                                'item_id' => $item->id,
                                'user_id' => $request->user()->id,
                                'order_id' => $order->id,
                                'quantity' => $quantity,
                                'type' => 'sold',
                                'price_at_the_time' => $item->price,
                                'total' => $subtotal,
                                'description' => "Penjualan via Order #{$order->id}",
                            ]);
                        }
                    }

                    // Handle image upload for custom bouquet unit
                    if (($detail['mode'] ?? null) === 'custom' && $request->hasFile("details.{$index}.custom_image")) {                        $customUnit = BouquetUnit::find($detail['bouquet_unit_id']);
                        if ($customUnit) {
                            $customUnit->addMediaFromRequest("details.{$index}.custom_image")
                                ->toMediaCollection('images');
                        }
                    }
                }

                $this->storeDeliveryIfNeeded($order, $validated);
                $this->syncOrderShippingExpenseAction->handle($order, $request->user()->id);

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
                        'items_total' => (float) $itemsTotal,
                        'shipping_fee' => $shippingFee,
                        'total' => (float) $total,
                        'down_payment' => $downPayment,
                        'payment_status' => $paymentStatus,
                    ])
                    ->log('order.created');

                return $order->load(['orderDetails', 'customer']);
            });
        } catch (QueryException $exception) {
            if ($this->isDuplicateRequestIdException($exception, $requestId)) {
                $existingOrder = $this->findExistingOrderByRequestId($requestId);
                if ($existingOrder) {
                    return $existingOrder;
                }
            }

            throw $exception;
        }
    }

    private function findExistingOrderByRequestId(string $requestId): ?Order
    {
        if ($requestId === '') {
            return null;
        }

        $order = Order::query()
            ->where('request_id', $requestId)
            ->first();

        return $order?->loadMissing(['orderDetails', 'customer']);
    }

    private function isDuplicateRequestIdException(QueryException $exception, string $requestId): bool
    {
        if ($requestId === '') {
            return false;
        }

        $sqlState = (string) ($exception->errorInfo[0] ?? '');
        if ($sqlState !== '23000' && $sqlState !== '23505') {
            return false;
        }

        $message = strtolower($exception->getMessage());

        return str_contains($message, 'orders_request_id_unique')
            || str_contains($message, 'request_id');
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
            $price = $unit->price;
            $money = $detail['money_bouquet'] ?? 0;

            return (float) ($price + $money) * $quantity;
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
            return $detail;
        }

        if (! $request->user()->can('input custom bouquet')) {
            abort(403, 'Anda tidak memiliki izin untuk membuat custom bouquet.');
        }

        $customUnit = $this->createCustomBouquetUnit($detail);
        $detail['bouquet_unit_id'] = $customUnit->id;

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
            'serial_number' => ! empty($detail['custom_serial_number'])
                ? trim((string) $detail['custom_serial_number'])
                : $this->generateCustomSerialNumber(),
            'name' => trim((string) $detail['custom_name']),
            'description' => $detail['custom_note'] ?? null,
            'price' => $detail['custom_price'],
            'is_active' => false,
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

    private function resolveShippingFee(array $validated): float
    {
        if (($validated['shipping_type'] ?? 'pickup') !== 'delivery') {
            return 0.0;
        }

        $shippingFee = (float) ($validated['shipping_fee'] ?? 0);

        return max(0, $shippingFee);
    }
}
