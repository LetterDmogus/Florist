<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreDeliveryRequest;
use App\Http\Requests\UpdateDeliveryRequest;
use App\Models\Delivery;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DeliveryController extends Controller
{
    public function index(Request $request): Response
    {
        $deliveries = Delivery::query()
            ->with(['order.customer'])
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
            ->when($request->date, fn ($q) => $q->whereHas('order', fn ($o) => $o->whereDate('shipping_date', $request->date)))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        $orderOptions = Order::query()
            ->with('customer')
            ->orderByDesc('shipping_date')
            ->orderByDesc('shipping_time')
            ->limit(300)
            ->get([
                'id',
                'customer_id',
                'shipping_date',
                'shipping_time',
                'shipping_type',
            ]);

        return Inertia::render('Deliveries/Index', [
            'deliveries' => $deliveries,
            'orderOptions' => $orderOptions->map(fn (Order $order): array => [
                'id' => $order->id,
                'customer_name' => $order->customer?->name,
                'customer_phone_number' => $order->customer?->phone_number,
                'shipping_date' => $order->shipping_date?->format('Y-m-d'),
                'shipping_time' => (string) $order->shipping_time,
                'shipping_type' => $order->shipping_type,
            ]),
            'filters' => $request->only('search', 'date', 'trashed'),
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
