<?php

declare(strict_types=1);

namespace App\Http\Controllers;

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
        $deliveries = Delivery::with(['order.customer'])
            ->when($request->search, fn ($q) => $q->where('recipient_name', 'like', "%{$request->search}%")
                ->orWhere('recipient_phone', 'like', "%{$request->search}%"))
            ->when($request->date, fn ($q) => $q->whereHas('order', fn ($o) => $o->whereDate('shipping_date', $request->date)))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Deliveries/Index', [
            'deliveries' => $deliveries,
            'filters' => $request->only('search', 'date'),
        ]);
    }

    public function store(Request $request, Order $order): RedirectResponse
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
        $delivery->update($request->validated());

        return redirect()->route('orders.show', $delivery->order_id)
            ->with('success', 'Informasi pengiriman berhasil diperbarui.');
    }

    public function destroy(Delivery $delivery): RedirectResponse
    {
        $orderId = $delivery->order_id;
        $delivery->delete();

        return redirect()->route('orders.show', $orderId)
            ->with('success', 'Data pengiriman berhasil dihapus.');
    }
}
