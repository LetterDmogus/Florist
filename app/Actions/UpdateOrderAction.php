<?php

declare(strict_types=1);

namespace App\Actions;

use App\Http\Requests\UpdateOrderRequest;
use App\Models\BouquetCategory;
use App\Models\BouquetType;
use App\Models\BouquetUnit;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\ItemUnit;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\ReportEntry;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class UpdateOrderAction
{
    public function __construct(
        private readonly SyncOrderShippingExpenseAction $syncOrderShippingExpenseAction,
    ) {}

    public function handle(UpdateOrderRequest $request, Order $order): Order
    {
        return DB::transaction(function () use ($request, $order): Order {
            $validated = $request->validated();
            $order->load(['orderDetails', 'delivery']);

            // 1. Update Customer jika berubah
            $customerId = $this->resolveCustomerId($validated);
            
            // 2. Resolve data detail baru
            $newDetailsData = collect($validated['details']);
            
            // 3. Identifikasi item yang dihapus
            $existingDetailIds = $order->orderDetails->pluck('id')->all();
            $newDetailIds = $newDetailsData->pluck('id')->filter()->all();
            $removedDetailIds = array_diff($existingDetailIds, $newDetailIds);

            // Proses Penghapusan
            foreach ($removedDetailIds as $detailId) {
                $detail = OrderDetail::find($detailId);
                if ($detail) {
                    $this->handleRemovedDetail($detail, $request->user()->id);
                }
            }

            // 4. Proses Update atau Create detail
            $resolvedDetails = [];
            foreach ($validated['details'] as $index => $detailInput) {
                $resolvedDetails[] = $this->processDetail($request, $order, $detailInput, $index);
            }

            // 5. Hitung ulang total
            $itemsTotal = collect($resolvedDetails)->sum('subtotal');
            $shippingFee = (float) ($validated['shipping_fee'] ?? 0);
            $total = $itemsTotal + $shippingFee;
            $downPayment = (float) ($validated['down_payment'] ?? 0);

            if ($downPayment > $itemsTotal) {
                throw ValidationException::withMessages([
                    'down_payment' => 'Down payment tidak boleh melebihi total item (Subtotal + Uang Buket).',
                ]);
            }

            // 6. Update Metadata Order
            $order->update([
                'customer_id' => $customerId,
                'total' => $total,
                'shipping_date' => $validated['shipping_date'],
                'shipping_time' => $validated['shipping_time'],
                'shipping_type' => $validated['shipping_type'],
                'shipping_fee' => $shippingFee,
                'down_payment' => $downPayment > 0 ? $downPayment : null,
                'payment_status' => $validated['payment_status'],
                'order_status' => $validated['order_status'],
                'description' => $validated['description'] ?? null,
            ]);

            // 7. Update Delivery
            $this->updateDelivery($order, $validated);

            // 8. Sync Laporan Ongkir
            $this->syncOrderShippingExpenseAction->handle($order, $request->user()->id);

            activity('orders')
                ->causedBy($request->user())
                ->performedOn($order)
                ->event('updated')
                ->withProperties([
                    'total' => $total,
                    'details_count' => count($resolvedDetails),
                ])
                ->log('order.updated');

            return $order->refresh()->load(['orderDetails.bouquetUnit', 'orderDetails.inventoryItem', 'customer', 'delivery']);
        });
    }

    private function processDetail(UpdateOrderRequest $request, Order $order, array $detailInput, int $index): OrderDetail
    {
        $id = $detailInput['id'] ?? null;
        $itemType = $detailInput['item_type'];
        $quantity = (int) ($detailInput['quantity'] ?? 1);
        
        // Resolve subtotal based on current prices
        $subtotal = 0;
        $bouquetUnitId = $detailInput['bouquet_unit_id'] ?? null;
        $inventoryItemId = $detailInput['inventory_item_id'] ?? null;
        $moneyBouquetInput = $detailInput['money_amount'] ?? $detailInput['money_bouquet'] ?? null;

        if ($itemType === 'bouquet') {
            if (($detailInput['mode'] ?? 'catalog') === 'custom' && !$id) {
                // Create new custom unit only if it's a new detail line
                $customUnit = $this->createCustomBouquetUnit($detailInput);
                $bouquetUnitId = $customUnit->id;
            }
            
            $unit = BouquetUnit::findOrFail($bouquetUnitId);
            $moneyBouquetRecord = $moneyBouquetInput;
            $subtotal = (float) $unit->price + (float) ($moneyBouquetRecord ?? 0);
            
            $quantity = 1;
        } else {
            $item = ItemUnit::findOrFail($inventoryItemId);
            $subtotal = (float) $item->price * $quantity;
            $moneyBouquetRecord = null;
        }

        $data = [
            'order_id' => $order->id,
            'item_type' => $itemType,
            'quantity' => $quantity,
            'subtotal' => $subtotal,
            'bouquet_unit_id' => $bouquetUnitId,
            'inventory_item_id' => $inventoryItemId,
            'money_bouquet' => $moneyBouquetRecord,
            'greeting_card' => $detailInput['greeting_card'] ?? null,
            'sender_name' => $detailInput['sender_name'] ?? null,
        ];

        if ($id) {
            $detail = OrderDetail::findOrFail($id);
            
            // Handle stock adjustment if inventory item or quantity changed
            if ($itemType === 'inventory_item') {
                $this->adjustStock($detail, $data, $request->user()->id);
            }
            
            $detail->update($data);
        } else {
            $detail = OrderDetail::create($data);
            
            // Handle initial stock reduction for new inventory item
            if ($itemType === 'inventory_item') {
                $item = ItemUnit::lockForUpdate()->find($inventoryItemId);
                if ($item) {
                    $item->decrement('stock', $quantity);
                    StockMovement::create([
                        'item_id' => $item->id,
                        'user_id' => $request->user()->id,
                        'order_id' => $order->id,
                        'quantity' => $quantity,
                        'type' => 'sold',
                        'price_at_the_time' => $item->price,
                        'total' => $subtotal,
                        'description' => "Penjualan via Update Order #{$order->id}",
                    ]);
                }
            }
        }

        // Handle image for custom
        if (($detailInput['mode'] ?? null) === 'custom' && $request->hasFile("details.{$index}.custom_image")) {
            $unit = BouquetUnit::find($bouquetUnitId);
            if ($unit) {
                $unit->addMediaFromRequest("details.{$index}.custom_image")->toMediaCollection('images');
            }
        }

        return $detail;
    }

    private function handleRemovedDetail(OrderDetail $detail, int $userId): void
    {
        $orderId = $detail->order_id;

        // 1. Revert Stock if inventory
        if ($detail->item_type === 'inventory_item' && $detail->inventory_item_id) {
            $item = ItemUnit::lockForUpdate()->find($detail->inventory_item_id);
            if ($item) {
                $item->increment('stock', $detail->quantity);
            }
            // Remove the specific stock movement
            StockMovement::where('order_id', $orderId)
                ->where('item_id', $detail->inventory_item_id)
                ->where('type', 'sold')
                ->where('quantity', $detail->quantity)
                ->first()?->forceDelete();
        }

        // 2. Delete Custom Bouquet Unit
        if ($detail->item_type === 'bouquet' && $detail->bouquet_unit_id) {
            $unit = $detail->bouquetUnit;
            if ($unit && $unit->type && $unit->type->is_custom) {
                $unitId = $unit->id;
                $detail->update(['bouquet_unit_id' => null]);
                
                $isStillUsed = OrderDetail::where('bouquet_unit_id', $unitId)->exists();
                if (!$isStillUsed) {
                    $unit->forceDelete();
                }
            }
        }

        $detail->forceDelete();
    }

    private function adjustStock(OrderDetail $oldDetail, array $newData, int $userId): void
    {
        $oldItemId = $oldDetail->inventory_item_id;
        $newItemId = $newData['inventory_item_id'];
        $oldQty = (int) $oldDetail->quantity;
        $newQty = (int) $newData['quantity'];

        if ($oldItemId === $newItemId) {
            if ($oldQty !== $newQty) {
                $item = ItemUnit::lockForUpdate()->find($oldItemId);
                if ($item) {
                    $diff = $newQty - $oldQty;
                    if ($diff > 0) {
                        $item->decrement('stock', $diff);
                    } else {
                        $item->increment('stock', abs($diff));
                    }
                    
                    // Update movement record
                    StockMovement::where('order_id', $oldDetail->order_id)
                        ->where('item_id', $oldItemId)
                        ->where('type', 'sold')
                        ->update([
                            'quantity' => $newQty,
                            'total' => $newData['subtotal']
                        ]);
                }
            }
        } else {
            // Revert old item
            $oldItem = ItemUnit::lockForUpdate()->find($oldItemId);
            if ($oldItem) {
                $oldItem->increment('stock', $oldQty);
            }
            StockMovement::where('order_id', $oldDetail->order_id)
                ->where('item_id', $oldItemId)
                ->where('type', 'sold')
                ->forceDelete();

            // Apply new item
            $newItem = ItemUnit::lockForUpdate()->find($newItemId);
            if ($newItem) {
                $newItem->decrement('stock', $newQty);
                StockMovement::create([
                    'item_id' => $newItem->id,
                    'user_id' => $userId,
                    'order_id' => $oldDetail->order_id,
                    'quantity' => $newQty,
                    'type' => 'sold',
                    'price_at_the_time' => $newItem->price,
                    'total' => $newData['subtotal'],
                    'description' => "Update item via Order #{$oldDetail->order_id}",
                ]);
            }
        }
    }

    private function updateDelivery(Order $order, array $validated): void
    {
        if ($validated['shipping_type'] === 'pickup') {
            $order->delivery()?->forceDelete();
            return;
        }

        $payload = [
            'recipient_name' => $validated['delivery_recipient_name'] ?? '',
            'recipient_phone' => $validated['delivery_recipient_phone'] ?? '',
            'full_address' => $validated['delivery_full_address'] ?? '',
        ];

        if ($order->delivery) {
            $order->delivery->update($payload);
        } else {
            $order->delivery()->create($payload);
        }
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

    private function createCustomBouquetUnit(array $detail): BouquetUnit
    {
        $category = BouquetCategory::query()->findOrFail($detail['custom_category_id']);
        $customType = BouquetType::where('category_id', $category->id)->where('is_custom', true)->first();

        if (!$customType) {
            $customType = BouquetType::create([
                'category_id' => $category->id,
                'name' => 'Custom',
                'is_custom' => true,
            ]);
        }

        return BouquetUnit::create([
            'type_id' => $customType->id,
            'serial_number' => $detail['custom_serial_number'] ?? $this->generateSerialNumber(),
            'name' => $detail['custom_name'],
            'description' => $detail['custom_note'] ?? null,
            'price' => $detail['custom_price'],
            'is_active' => false,
        ]);
    }

    private function generateSerialNumber(): string
    {
        return 'CUST-' . strtoupper(Str::random(8));
    }
}
