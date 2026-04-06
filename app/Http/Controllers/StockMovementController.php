<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateStockMovementAction;
use App\Http\Requests\StoreStockMovementRequest;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\ItemUnit;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockMovementController extends Controller
{
    public function index(Request $request): Response
    {
        [$sortBy, $sortDir] = $this->resolveSort(
            $request,
            ['created_at', 'type', 'quantity', 'price_at_the_time', 'total', 'item_id', 'order_id', 'description'],
            'created_at',
            'desc',
        );

        $movements = StockMovement::query()
            ->with([
                'item:id,name,serial_number,stock,price',
                'order:id,customer_id',
                'order.customer:id,name',
            ])
            ->when($request->search, function ($q) use ($request) {
                $search = (string) $request->search;

                $q->where(function ($builder) use ($search): void {
                    if (is_numeric($search)) {
                        $builder
                            ->orWhere('id', (int) $search)
                            ->orWhere('order_id', (int) $search);
                    }

                    $builder
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('item', fn ($itemQuery) => $itemQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('serial_number', 'like', "%{$search}%"));
                });
            })
            ->when($request->item_id, fn ($q) => $q->where('item_id', $request->item_id))
            ->when($request->type, fn ($q) => $q->where('type', $request->type))
            ->when($request->date_from, fn ($q) => $q->whereDate('created_at', '>=', $request->date_from))
            ->when($request->date_to, fn ($q) => $q->whereDate('created_at', '<=', $request->date_to))
            ->orderBy($sortBy, $sortDir)
            ->paginate(30)
            ->withQueryString()
            ->through(function (StockMovement $movement): array {
                return [
                    'id' => $movement->id,
                    'created_at' => $movement->created_at?->toIso8601String(),
                    'type' => $movement->type,
                    'quantity' => (int) $movement->quantity,
                    'price_at_the_time' => (float) $movement->price_at_the_time,
                    'total' => (float) $movement->total,
                    'description' => $movement->description,
                    'order_id' => $movement->order_id,
                    'reference_type' => $movement->order_id ? null : 'Manual',
                    'item' => $movement->item ? [
                        'id' => $movement->item->id,
                        'name' => $movement->item->name,
                        'serial_number' => $movement->item->serial_number,
                        'stock' => (int) $movement->item->stock,
                        'price' => (float) $movement->item->price,
                    ] : null,
                    'order' => $movement->order ? [
                        'id' => $movement->order->id,
                        'customer' => $movement->order->customer ? [
                            'id' => $movement->order->customer->id,
                            'name' => $movement->order->customer->name,
                        ] : null,
                    ] : null,
                ];
            });

        $customers = Customer::query()
            ->orderBy('name')
            ->get(['id', 'name', 'phone_number', 'aliases'])
            ->map(fn (Customer $customer): array => [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone_number' => $customer->phone_number,
                'aliases' => $customer->aliases,
            ])
            ->values()
            ->all();

        $deliveryReferences = Delivery::query()
            ->orderByDesc('created_at')
            ->limit(200)
            ->get(['id', 'recipient_name', 'recipient_phone', 'full_address'])
            ->map(fn (Delivery $delivery): array => [
                'id' => $delivery->id,
                'recipient_name' => $delivery->recipient_name,
                'recipient_phone' => $delivery->recipient_phone,
                'full_address' => $delivery->full_address,
            ])
            ->values()
            ->all();

        return Inertia::render('StockMovements/Index', [
            'movements' => $movements,
            'items' => [],
            'customers' => $customers,
            'deliveryReferences' => $deliveryReferences,
            'filters' => $request->only('search', 'item_id', 'type', 'date_from', 'date_to', 'sort_by', 'sort_dir'),
            'typeOptions' => collect(StockMovement::TYPE_LABELS)
                ->map(fn (string $label, string $value): array => [
                    'value' => $value,
                    'label' => $label,
                ])
                ->values()
                ->all(),
            'canCreateStockMovement' => $request->user()?->can('stock.manage') ?? false,
        ]);
    }

    public function show(Request $request, StockMovement $stock_movement): Response|JsonResponse
    {
        if ($request->wantsJson()) {
            return response()->json([
                'item' => $stock_movement,
                'audit_trail' => $stock_movement->getAuditTrail(),
            ]);
        }

        return Inertia::render('StockMovements/Index', [
            'item' => $stock_movement,
            'audit_trail' => $stock_movement->getAuditTrail(),
        ]);
    }

    public function itemLookup(Request $request): JsonResponse
    {
        $search = trim((string) $request->string('search')->toString());
        $limit = max(5, min(10, (int) $request->integer('limit', 8)));

        if ($search === '') {
            return response()->json([
                'data' => [],
            ]);
        }

        $startsWith = "{$search}%";
        $contains = "%{$search}%";

        $items = ItemUnit::query()
            ->select(['id', 'name', 'serial_number', 'stock', 'price'])
            ->where(function ($query) use ($contains): void {
                $query
                    ->where('name', 'like', $contains)
                    ->orWhere('serial_number', 'like', $contains);
            })
            ->orderByRaw(
                'CASE
                    WHEN serial_number LIKE ? THEN 0
                    WHEN name LIKE ? THEN 1
                    WHEN name LIKE ? THEN 2
                    ELSE 3
                END',
                [$startsWith, $startsWith, $contains],
            )
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn (ItemUnit $item): array => [
                'id' => $item->id,
                'name' => $item->name,
                'serial_number' => $item->serial_number,
                'stock' => (int) $item->stock,
                'price' => (float) $item->price,
            ])
            ->values()
            ->all();

        return response()->json([
            'data' => $items,
        ]);
    }

    public function store(StoreStockMovementRequest $request, CreateStockMovementAction $action): RedirectResponse
    {
        $result = $action->handle($request);
        $order = $result['order'] ?? null;

        $message = 'Pergerakan stok berhasil dicatat.';
        if ($order) {
            $message .= " Order baru #{$order->id} berhasil dibuat.";
        }

        return redirect()->route('stock-movements.index')
            ->with('success', $message);
    }
}
