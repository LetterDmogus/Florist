<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateOrderAction;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Models\BouquetUnit;
use App\Models\Customer;
use App\Models\ItemUnit;
use App\Models\Order;
use App\Models\OrderDetail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function index(Request $request): Response
    {
        $orders = Order::with(['customer', 'user'])
            ->when($request->search, fn ($q) => $q->whereHas('customer', fn ($c) => $c->where('name', 'like', "%{$request->search}%")))
            ->when($request->payment_status, fn ($q) => $q->where('payment_status', $request->payment_status))
            ->when($request->order_status, fn ($q) => $q->where('order_status', $request->order_status))
            ->when($request->shipping_type, fn ($q) => $q->where('shipping_type', $request->shipping_type))
            ->when($request->date_from, fn ($q) => $q->whereDate('shipping_date', '>=', $request->date_from))
            ->when($request->date_to, fn ($q) => $q->whereDate('shipping_date', '<=', $request->date_to))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Orders/Index', [
            'orders' => $orders,
            'filters' => $request->only('search', 'payment_status', 'order_status', 'shipping_type', 'date_from', 'date_to'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Orders/Create', [
            'customers' => Customer::orderBy('name')->get(['id', 'name', 'phone_number']),
            'bouquetUnits' => BouquetUnit::with('type.category')->orderBy('name')->get(),
            'inventoryItems' => ItemUnit::with('category')->orderBy('name')->get(['id', 'name', 'serial_number', 'price', 'individual', 'category_id']),
        ]);
    }

    public function store(StoreOrderRequest $request, CreateOrderAction $action): RedirectResponse
    {
        $order = $action->handle($request);

        return redirect()->route('orders.show', $order)
            ->with('success', 'Order berhasil dibuat.');
    }

    public function show(Order $order): Response
    {
        $order->load([
            'customer',
            'user',
            'delivery',
            'orderDetails.bouquetUnit.type',
            'orderDetails.inventoryItem.category',
        ]);

        return Inertia::render('Orders/Show', [
            'order' => $order,
        ]);
    }

    public function update(UpdateOrderRequest $request, Order $order): RedirectResponse
    {
        $order->update($request->validated());

        return redirect()->route('orders.show', $order)
            ->with('success', 'Order berhasil diperbarui.');
    }

    public function destroy(Order $order): RedirectResponse
    {
        $order->delete();

        return redirect()->route('orders.index')
            ->with('success', 'Order berhasil dihapus.');
    }

    // ─── Order Detail Methods ─────────────────────────────────────────────────

    public function storeDetail(Request $request, Order $order): RedirectResponse
    {
        $validated = $request->validate([
            'item_type' => ['required', 'in:bouquet,inventory_item'],
            'quantity' => ['required', 'integer', 'min:1'],
            'bouquet_unit_id' => ['nullable', 'integer', 'exists:bouquet_units,id'],
            'inventory_item_id' => ['nullable', 'integer', 'exists:item_units,id'],
            'money_bouquet' => ['nullable', 'numeric', 'min:0'],
            'greeting_card' => ['nullable', 'string'],
            'sender_name' => ['nullable', 'string', 'max:255'],
        ]);

        $subtotal = $this->resolveSubtotal($validated);

        $order->orderDetails()->create([...$validated, 'subtotal' => $subtotal]);

        // Recalculate order total
        $order->update(['total' => $order->orderDetails()->sum('subtotal')]);

        return redirect()->route('orders.show', $order)
            ->with('success', 'Item berhasil ditambahkan ke order.');
    }

    public function destroyDetail(Order $order, OrderDetail $orderDetail): RedirectResponse
    {
        $orderDetail->delete();

        // Recalculate order total
        $order->update(['total' => $order->orderDetails()->sum('subtotal')]);

        return redirect()->route('orders.show', $order)
            ->with('success', 'Item berhasil dihapus dari order.');
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function resolveSubtotal(array $detail): float
    {
        if ($detail['item_type'] === 'bouquet') {
            $unit = BouquetUnit::findOrFail($detail['bouquet_unit_id']);
            return (float) $unit->price * $detail['quantity'];
        }

        $unit = ItemUnit::findOrFail($detail['inventory_item_id']);
        return (float) $unit->price * $detail['quantity'];
    }
}
