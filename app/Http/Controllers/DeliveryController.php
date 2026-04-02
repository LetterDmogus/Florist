<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreDeliveryRequest;
use App\Http\Requests\UpdateDeliveryRequest;
use App\Models\Delivery;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DeliveryController extends Controller
{
    public function index(Request $request): Response
    {
        [$sortBy, $sortDir] = $this->resolveSort(
            $request,
            ['order_id', 'shipping_date', 'shipping_time', 'recipient_name', 'recipient_phone', 'full_address', 'created_at', 'updated_at'],
            'created_at',
            'desc',
        );

        $deliveriesQuery = Delivery::query()
            ->with([
                'order:id,customer_id,shipping_date,shipping_time,shipping_type',
                'order.customer:id,name,phone_number',
            ])
            ->when($request->boolean('trashed'), fn ($q) => $q->onlyTrashed())
            ->when($request->search, function ($q) use ($request) {
                $search = (string) $request->search;

                $q->where(function ($builder) use ($search): void {
                    if (is_numeric($search)) {
                        $builder->orWhere('order_id', (int) $search);
                    }

                    $builder
                        ->orWhere('recipient_name', 'like', "%{$search}%")
                        ->orWhere('recipient_phone', 'like', "%{$search}%")
                        ->orWhere('full_address', 'like', "%{$search}%")
                        ->orWhereHas('order.customer', fn ($customerQuery) => $customerQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('phone_number', 'like', "%{$search}%"));
                });
            })
            ->when($request->date, fn ($q) => $q->whereHas('order', fn ($o) => $o->whereDate('shipping_date', $request->date)));

        if ($sortBy === 'shipping_date') {
            $deliveriesQuery
                ->orderBy(
                    Order::query()
                        ->select('shipping_date')
                        ->whereColumn('orders.id', 'deliveries.order_id')
                        ->limit(1),
                    $sortDir,
                )
                ->orderBy(
                    Order::query()
                        ->select('shipping_time')
                        ->whereColumn('orders.id', 'deliveries.order_id')
                        ->limit(1),
                    $sortDir,
                );
        } elseif ($sortBy === 'shipping_time') {
            $deliveriesQuery
                ->orderBy(
                    Order::query()
                        ->select('shipping_time')
                        ->whereColumn('orders.id', 'deliveries.order_id')
                        ->limit(1),
                    $sortDir,
                )
                ->orderBy(
                    Order::query()
                        ->select('shipping_date')
                        ->whereColumn('orders.id', 'deliveries.order_id')
                        ->limit(1),
                    $sortDir,
                );
        } else {
            $deliveriesQuery->orderBy($sortBy, $sortDir);
        }

        $deliveries = $deliveriesQuery
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Deliveries/Index', [
            'deliveries' => $deliveries,
            'orderOptions' => [],
            'filters' => $request->only('search', 'date', 'trashed', 'sort_by', 'sort_dir'),
        ]);
    }

    public function orderLookup(Request $request): JsonResponse
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

        $orders = Order::query()
            ->select(['id', 'customer_id', 'shipping_date', 'shipping_time', 'shipping_type'])
            ->with(['customer:id,name,phone_number'])
            ->where(function ($query) use ($search, $contains): void {
                if (is_numeric($search)) {
                    $query->orWhere('id', (int) $search);
                }

                $query->orWhereHas('customer', function ($customerQuery) use ($contains): void {
                    $customerQuery
                        ->where('name', 'like', $contains)
                        ->orWhere('phone_number', 'like', $contains);
                });
            })
            ->orderByRaw(
                'CASE
                    WHEN CAST(id AS CHAR) LIKE ? THEN 0
                    WHEN id = ? THEN 1
                    ELSE 2
                END',
                [$startsWith, is_numeric($search) ? (int) $search : 0],
            )
            ->orderByDesc('shipping_date')
            ->orderByDesc('shipping_time')
            ->limit($limit)
            ->get()
            ->map(fn (Order $order): array => [
                'id' => $order->id,
                'customer_name' => $order->customer?->name,
                'customer_phone_number' => $order->customer?->phone_number,
                'shipping_date' => $order->shipping_date?->format('Y-m-d'),
                'shipping_time' => (string) $order->shipping_time,
                'shipping_type' => $order->shipping_type,
            ])
            ->values()
            ->all();

        return response()->json([
            'data' => $orders,
        ]);
    }

    public function store(StoreDeliveryRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated): void {
            $order = Order::query()->findOrFail($validated['order_id']);

            Delivery::create($validated);
            $order->update(['shipping_type' => 'delivery']);
        });

        return redirect()->route('deliveries.index')
            ->with('success', 'Data delivery berhasil ditambahkan.');
    }

    public function storeFromOrder(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate([
            'recipient_name' => ['required', 'string', 'max:255'],
            'recipient_phone' => ['required', 'string', 'max:20'],
            'full_address' => ['required', 'string'],
        ]);

        DB::transaction(function () use ($order, $validated): void {
            $order->delivery()->updateOrCreate(
                ['order_id' => $order->id],
                $validated,
            );

            // Tandai order sebagai delivery (sudah ada informasi pengiriman)
            $order->update(['shipping_type' => 'delivery']);
        });

        return redirect()->route('orders.show', $order)
            ->with('success', 'Informasi pengiriman berhasil disimpan.');
    }

    public function update(UpdateDeliveryRequest $request, Delivery $delivery): RedirectResponse
    {
        $validated = $request->validated();

        DB::transaction(function () use ($delivery, $validated): void {
            $delivery->update($validated);

            if (isset($validated['order_id'])) {
                Order::query()
                    ->findOrFail($validated['order_id'])
                    ->update(['shipping_type' => 'delivery']);
            }
        });

        return redirect()->route('deliveries.index')
            ->with('success', 'Informasi pengiriman berhasil diperbarui.');
    }

    public function destroy(Delivery $delivery): RedirectResponse
    {
        $delivery->delete();

        return redirect()->route('deliveries.index')
            ->with('success', 'Data pengiriman berhasil dihapus.');
    }

    public function restore(int $id): RedirectResponse
    {
        Delivery::withTrashed()->findOrFail($id)->restore();

        return redirect()->route('deliveries.index')
            ->with('success', 'Data delivery berhasil dipulihkan.');
    }

    public function forceDelete(int $id): RedirectResponse
    {
        Delivery::withTrashed()->findOrFail($id)->forceDelete();

        return redirect()->route('deliveries.index')
            ->with('success', 'Data delivery berhasil dihapus permanen.');
    }
}
