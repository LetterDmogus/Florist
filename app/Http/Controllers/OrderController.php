<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateOrderAction;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Models\BouquetCategory;
use App\Models\BouquetUnit;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\ItemUnit;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\SiteSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function print(Order $order): Response
    {
        $order->load([
            'customer',
            'user',
            'orderDetails.bouquetUnit.type',
            'orderDetails.inventoryItem.category',
        ]);

        return Inertia::render('Orders/Receipt', [
            'order' => $order,
            'settings' => [
                'store_name' => SiteSetting::getValue('store_name', 'Bees Fleur Florist'),
                'address' => SiteSetting::getValue('address', 'Jl. Mawar No. 123, Jakarta'),
                'phone' => SiteSetting::getValue('phone', '081234567890'),
                'receipt_note' => SiteSetting::getValue('receipt_note', 'Terima kasih telah berbelanja di Bees Fleur!'),
            ],
        ]);
    }

    public function index(Request $request): Response
    {
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');

        $orders = Order::with([
            'customer',
            'user',
            'delivery',
            'orderDetails.bouquetUnit',
            'orderDetails.inventoryItem',
        ])
            ->when($request->search, fn ($q) => $q->whereHas('customer', fn ($c) => $c->where('name', 'like', "%{$request->search}%")))
            ->when($request->payment_status, fn ($q) => $q->where('payment_status', $request->payment_status))
            ->when($request->order_status, fn ($q) => $q->where('order_status', $request->order_status))
            ->when($request->shipping_type, fn ($q) => $q->where('shipping_type', $request->shipping_type))
            ->when($request->date_from, fn ($q) => $q->whereDate('shipping_date', '>=', $request->date_from))
            ->when($request->date_to, fn ($q) => $q->whereDate('shipping_date', '<=', $request->date_to))
            ->orderBy($sortBy, $sortDir)
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Orders/Index', [
            'orders' => $orders,
            'filters' => $request->only('search', 'payment_status', 'order_status', 'shipping_type', 'date_from', 'date_to', 'sort_by', 'sort_dir'),
            ...$this->cashierPayload($request),
        ]);
    }

    public function statusIndex(Request $request): Response
    {
        abort_unless($request->user()?->hasAnyRole(['super-admin', 'admin']), 403);

        $orderStatusFilter = $this->normalizeOrderStatusFilter((string) $request->string('order_status')->toString());
        $search = trim((string) $request->string('search')->toString());
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');

        $orders = Order::with([
            'customer',
            'user',
            'delivery',
            'orderDetails.bouquetUnit',
            'orderDetails.inventoryItem',
        ])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($builder) use ($search): void {
                    if (is_numeric($search)) {
                        $builder->orWhere('id', (int) $search);
                    }

                    $builder->orWhereHas('customer', function ($customerQuery) use ($search): void {
                        $customerQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('phone_number', 'like', "%{$search}%");
                    });
                });
            })
            ->when($orderStatusFilter !== '', fn ($q) => $q->where('order_status', $orderStatusFilter))
            ->orderBy($sortBy, $sortDir)
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Orders/Status', [
            'orders' => $orders,
            'filters' => [
                'order_status' => $orderStatusFilter,
                'search' => $search,
                'sort_by' => $sortBy,
                'sort_dir' => $sortDir,
            ],
            'orderStatusSummary' => $this->buildOrderStatusSummary(),
            'canManageOrderStatus' => true,
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->index($request);
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
            'orderStatusLabels' => Order::ORDER_STATUS_LABELS,
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

    public function updateStatus(Request $request, Order $order): RedirectResponse
    {
        abort_unless($request->user()?->hasAnyRole(['super-admin', 'admin']), 403);

        $validated = $request->validate([
            'order_status' => ['required', Rule::in(Order::ORDER_STATUSES)],
        ]);

        $finalStatus = Order::ORDER_STATUSES[array_key_last(Order::ORDER_STATUSES)];
        if ($order->order_status === $finalStatus && $validated['order_status'] !== $finalStatus) {
            return redirect()->back()
                ->withErrors([
                    'order_status' => 'Order yang sudah di status terakhir tidak bisa dimundurkan lagi.',
                ]);
        }

        $order->update([
            'order_status' => $validated['order_status'],
        ]);

        return redirect()->back()
            ->with('success', 'Status order berhasil diperbarui.');
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
        if ($validated['item_type'] === 'bouquet' && ! isset($validated['money_bouquet'])) {
            $validated['money_bouquet'] = (float) BouquetUnit::query()
                ->findOrFail($validated['bouquet_unit_id'])
                ->price;
        }

        DB::transaction(function () use ($order, $validated, $subtotal): void {
            $order->orderDetails()->create([...$validated, 'subtotal' => $subtotal]);

            // Recalculate order total
            $order->update(['total' => $order->orderDetails()->sum('subtotal')]);
        });

        return redirect()->route('orders.show', $order)
            ->with('success', 'Item berhasil ditambahkan ke order.');
    }

    public function destroyDetail(Order $order, OrderDetail $orderDetail): RedirectResponse
    {
        DB::transaction(function () use ($order, $orderDetail): void {
            $orderDetail->delete();

            // Recalculate order total
            $order->update(['total' => $order->orderDetails()->sum('subtotal')]);
        });

        return redirect()->route('orders.show', $order)
            ->with('success', 'Item berhasil dihapus dari order.');
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function resolveSubtotal(array $detail): float
    {
        if ($detail['item_type'] === 'bouquet') {
            $unit = BouquetUnit::findOrFail($detail['bouquet_unit_id']);
            $price = $detail['money_bouquet'] ?? $unit->price;

            return (float) $price * $detail['quantity'];
        }

        $unit = ItemUnit::findOrFail($detail['inventory_item_id']);

        return (float) $unit->price * $detail['quantity'];
    }

    private function cashierPayload(Request $request): array
    {
        return [
            'customers' => Customer::query()
                ->withCount(['orders', 'orderDetails'])
                ->withSum('orderDetails as order_items_count', 'quantity')
                ->orderBy('name')
                ->get(['id', 'name', 'phone_number']),
            'bouquetUnits' => BouquetUnit::query()
                ->with('type.category')
                ->orderBy('name')
                ->get(),
            'bouquetCategories' => BouquetCategory::query()
                ->orderBy('name')
                ->get(['id', 'name']),
            'deliveryReferences' => Delivery::query()
                ->orderByDesc('created_at')
                ->limit(200)
                ->get(['id', 'recipient_name', 'recipient_phone', 'full_address']),
            'canCustomBouquet' => (bool) $request->user()?->can('input custom bouquet'),
        ];
    }

    private function normalizeOrderStatusFilter(string $status): string
    {
        if ($status === '' || ! in_array($status, Order::ORDER_STATUSES, true)) {
            return '';
        }

        return $status;
    }

    private function buildOrderStatusSummary(): array
    {
        $statusCounts = Order::query()
            ->select('order_status', DB::raw('COUNT(*) as total'))
            ->whereIn('order_status', Order::ORDER_STATUSES)
            ->groupBy('order_status')
            ->pluck('total', 'order_status');

        return collect(Order::ORDER_STATUSES)
            ->map(fn (string $status): array => [
                'value' => $status,
                'label' => Order::ORDER_STATUS_LABELS[$status] ?? Str::headline($status),
                'count' => (int) ($statusCounts[$status] ?? 0),
            ])
            ->values()
            ->all();
    }
}
