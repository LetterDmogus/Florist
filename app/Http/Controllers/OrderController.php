<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateOrderAction;
use App\Actions\SyncOrderShippingExpenseAction;
use App\Actions\UpdateOrderAction;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderRequest;
use App\Models\BouquetCategory;
use App\Models\BouquetUnit;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\ItemUnit;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\ReportEntry;
use App\Models\SiteSetting;
use App\Models\StockMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
        $perPage = $this->resolvePerPage($request);
        [$sortBy, $sortDir] = $this->resolveSort(
            $request,
            ['id', 'customer_id', 'total', 'shipping_fee', 'shipping_date', 'shipping_time', 'shipping_type', 'payment_status', 'order_status', 'created_at', 'updated_at'],
            'created_at',
            'desc',
        );

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
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('Orders/Index', [
            'orders' => $orders,
            'filters' => [
                ...$request->only('search', 'payment_status', 'order_status', 'shipping_type', 'date_from', 'date_to', 'sort_by', 'sort_dir'),
                'per_page' => $perPage,
            ],
            ...$this->cashierPayload($request),
        ]);
    }

    public function statusIndex(Request $request): Response
    {
        $perPage = $this->resolvePerPage($request);
        $orderStatusFilter = $this->normalizeOrderStatusFilter((string) $request->string('order_status')->toString());
        $search = trim((string) $request->string('search')->toString());
        [$sortBy, $sortDir] = $this->resolveSort(
            $request,
            ['id', 'customer_id', 'total', 'shipping_fee', 'shipping_date', 'shipping_time', 'shipping_type', 'payment_status', 'order_status', 'created_at', 'updated_at'],
            'created_at',
            'desc',
        );

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
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('Orders/Status', [
            'orders' => $orders,
            'filters' => [
                'order_status' => $orderStatusFilter,
                'search' => $search,
                'sort_by' => $sortBy,
                'sort_dir' => $sortDir,
                'per_page' => $perPage,
            ],
            'orderStatusSummary' => $this->buildOrderStatusSummary(),
            'canManageOrderStatus' => (bool) $request->user()?->can('orders.status.update'),
            'canDeleteOrder' => (bool) $request->user()?->can('orders.delete'),
        ]);
    }

    public function create(Request $request): Response
    {
        return $this->index($request);
    }

    public function customerLookup(Request $request): JsonResponse
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

        $customers = Customer::query()
            ->select(['id', 'name', 'phone_number'])
            ->withCount(['orders', 'orderDetails'])
            ->withSum('orderDetails as order_items_count', 'quantity')
            ->where(function ($query) use ($contains): void {
                $query
                    ->where('name', 'like', $contains)
                    ->orWhere('phone_number', 'like', $contains)
                    ->orWhere('aliases', 'like', $contains);
            })
            ->orderByRaw(
                'CASE
                    WHEN phone_number LIKE ? THEN 0
                    WHEN name LIKE ? THEN 1
                    WHEN name LIKE ? THEN 2
                    ELSE 3
                END',
                [$startsWith, $startsWith, $contains],
            )
            ->orderBy('name')
            ->limit($limit)
            ->get();

        return response()->json([
            'data' => $customers,
        ]);
    }

    public function deliveryLookup(Request $request): JsonResponse
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

        $deliveries = Delivery::query()
            ->select(['id', 'recipient_name', 'recipient_phone', 'full_address'])
            ->where(function ($query) use ($contains): void {
                $query
                    ->where('recipient_name', 'like', $contains)
                    ->orWhere('recipient_phone', 'like', $contains)
                    ->orWhere('full_address', 'like', $contains);
            })
            ->orderByRaw(
                'CASE
                    WHEN recipient_phone LIKE ? THEN 0
                    WHEN recipient_name LIKE ? THEN 1
                    WHEN recipient_name LIKE ? THEN 2
                    WHEN full_address LIKE ? THEN 3
                    ELSE 4
                END',
                [$startsWith, $startsWith, $contains, $startsWith],
            )
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        return response()->json([
            'data' => $deliveries,
        ]);
    }

    public function store(StoreOrderRequest $request, CreateOrderAction $action): RedirectResponse
    {
        abort_unless($request->user()?->can('orders.create'), 403);

        $order = $action->handle($request);

        return redirect()->route('orders.status.index')
            ->with('success', 'Order berhasil dibuat.');
    }

    public function edit(Request $request, Order $order): Response
    {
        abort_unless($request->user()?->can('orders.edit'), 403);

        $order->load([
            'customer',
            'delivery',
            'orderDetails.bouquetUnit.type.category',
            'orderDetails.inventoryItem.category',
        ]);

        return Inertia::render('Orders/Edit', [
            'order' => $order,
            ...$this->cashierPayload($request),
        ]);
    }

    public function update(UpdateOrderRequest $request, Order $order, UpdateOrderAction $action): RedirectResponse
    {
        abort_unless($request->user()?->can('orders.edit'), 403);

        $action->handle($request, $order);

        return redirect()->route('orders.status.index')
            ->with('success', 'Order berhasil diperbarui.');
    }

    private function cashierPayload(Request $request): array
    {
        $catalogSearch = trim((string) $request->string('catalog_search')->toString());
        $catalogCategoryId = trim((string) $request->string('catalog_category_id')->toString());

        if ($catalogCategoryId !== '' && ! ctype_digit($catalogCategoryId)) {
            $catalogCategoryId = '';
        }

        $bouquetUnits = BouquetUnit::query()
            ->with(['type.category', 'media'])
            ->where('is_active', true)
            ->when($catalogSearch !== '', function ($query) use ($catalogSearch): void {
                $query->where(function ($builder) use ($catalogSearch): void {
                    $builder
                        ->where('name', 'like', "%{$catalogSearch}%")
                        ->orWhere('serial_number', 'like', "%{$catalogSearch}%")
                        ->orWhereHas('type', function ($typeQuery) use ($catalogSearch): void {
                            $typeQuery
                                ->where('name', 'like', "%{$catalogSearch}%")
                                ->orWhereHas('category', fn ($categoryQuery) => $categoryQuery->where('name', 'like', "%{$catalogSearch}%"));
                        });
                });
            })
            ->when(
                $catalogCategoryId !== '',
                fn ($query) => $query->whereHas('type', fn ($typeQuery) => $typeQuery->where('category_id', (int) $catalogCategoryId))
            )
            ->orderBy('name')
            ->paginate(12, ['*'], 'bouquet_page')
            ->withQueryString();

        $inventoryItems = ItemUnit::query()
            ->with(['category', 'media'])
            ->where('stock', '>', 0)
            ->when($catalogSearch !== '', function ($query) use ($catalogSearch): void {
                $query->where(function ($q) use ($catalogSearch) {
                    $q->where('name', 'like', "%{$catalogSearch}%")
                        ->orWhere('serial_number', 'like', "%{$catalogSearch}%");
                });
            })
            ->orderBy('name')
            ->paginate(12, ['*'], 'inventory_page')
            ->withQueryString();

        return [
            'bouquetUnits' => $bouquetUnits,
            'inventoryItems' => $inventoryItems,
            'bouquetCategories' => BouquetCategory::query()
                ->orderBy('name')
                ->get(['id', 'name']),
            'catalogFilters' => [
                'search' => $catalogSearch,
                'category_id' => $catalogCategoryId,
            ],
            'canCustomBouquet' => (bool) $request->user()?->can('input custom bouquet'),
        ];
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

    public function destroy(Request $request, Order $order): RedirectResponse
    {
        abort_unless($request->user()?->can('orders.delete'), 403);

        $orderId = $order->id;
        $needsReset = false;

        DB::transaction(function () use ($order, &$needsReset, $orderId): void {
            // 1. Ambil semua custom bouquet unit yang HANYA digunakan oleh order ini
            $customUnitIds = $order->orderDetails()
                ->where('item_type', 'bouquet')
                ->whereNotNull('bouquet_unit_id')
                ->get()
                ->filter(function (OrderDetail $detail) {
                    $unit = $detail->bouquetUnit;
                    return $unit && $unit->type && $unit->type->is_custom;
                })
                ->pluck('bouquet_unit_id')
                ->all();

            // 2. Jika ada item katalog, aktifkan kembali
            $order->orderDetails()
                ->where('item_type', 'bouquet')
                ->whereNotNull('bouquet_unit_id')
                ->get()
                ->each(function (OrderDetail $detail) {
                    $unit = $detail->bouquetUnit;
                    if ($unit && $unit->type && !$unit->type->is_custom) {
                        $unit->update(['is_active' => true]);
                    }
                });

            // 3. Kembalikan stok item inventory jika ada pergerakan stok penjualan
            StockMovement::where('order_id', $orderId)
                ->where('type', 'sold')
                ->get()
                ->each(function (StockMovement $movement) {
                    $item = $movement->item;
                    if ($item) {
                        $item->increment('stock', $movement->quantity);
                    }
                    $movement->forceDelete();
                });

            // 4. Putuskan hubungan dan hapus detail order (Penting: Hapus detail dulu!)
            $order->orderDetails()->update(['bouquet_unit_id' => null]);
            $order->orderDetails()->forceDelete();

            // 5. Hapus custom bouquet unit yang sudah tidak punya referensi lagi
            if (!empty($customUnitIds)) {
                foreach ($customUnitIds as $unitId) {
                    $isStillUsed = OrderDetail::where('bouquet_unit_id', $unitId)->exists();
                    if (!$isStillUsed) {
                        BouquetUnit::where('id', $unitId)->forceDelete();
                    }
                }
            }

            // 6. Hapus delivery dan laporan ongkir
            $order->delivery()?->forceDelete();
            ReportEntry::where('order_id', $orderId)
                ->where('category', 'shipping_expense')
                ->forceDelete();

            // 7. Hapus order utama
            $order->forceDelete();

            // 8. Cek apakah perlu reset numbering
            $maxId = (int) Order::query()->withTrashed()->max('id');
            if ($orderId >= $maxId) {
                $needsReset = true;
            }
        });

        // Jalankan DDL (ALTER TABLE) di luar transaksi untuk menghindari "Implicit Commit"
        if ($needsReset) {
            $maxId = (int) Order::query()->withTrashed()->max('id');
            $this->resetAutoIncrement('orders', $maxId);
        }

        return redirect()->route('orders.status.index')
            ->with('success', 'Order berhasil di-discard. Stok telah dikembalikan dan item katalog diaktifkan kembali.');
    }

    private function resetAutoIncrement(string $table, int $maxId): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'sqlite') {
            // For SQLite, if the table is empty, we should reset to 0
            DB::statement("UPDATE sqlite_sequence SET seq = ? WHERE name = ?", [$maxId, $table]);
        } elseif ($driver === 'mysql') {
            $nextId = $maxId + 1;
            DB::statement("ALTER TABLE `{$table}` AUTO_INCREMENT = {$nextId}");
        }
    }

    public function updateStatus(Request $request, Order $order): RedirectResponse
    {
        abort_unless($request->user()?->can('orders.status.update'), 403);

        $validated = $request->validate([
            'order_status' => ['required', Rule::in(Order::ORDER_STATUSES)],
        ]);

        $targetStatus = (string) $validated['order_status'];
        $result = DB::transaction(function () use ($order, $targetStatus, $request): array {
            $lockedOrder = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            $currentStatus = (string) $lockedOrder->order_status;
            $shippingType = (string) $lockedOrder->shipping_type;
            $paymentStatus = (string) $lockedOrder->payment_status;

            if (! Order::canTransition($currentStatus, $targetStatus, $shippingType)) {
                $allowedNext = Order::allowedNextStatuses($currentStatus, $shippingType);
                $allowedLabels = collect($allowedNext)
                    ->map(fn (string $status): string => Order::ORDER_STATUS_LABELS[$status] ?? Str::headline($status))
                    ->values()
                    ->all();

                return [
                    'ok' => false,
                    'allowed_labels' => $allowedLabels,
                ];
            }

            if ($currentStatus === $targetStatus) {
                return [
                    'ok' => true,
                    'changed' => false,
                ];
            }

            if ($targetStatus === 'completed' && $paymentStatus !== 'paid') {
                return [
                    'ok' => false,
                    'error_message' => 'Order harus dilunasi terlebih dahulu sebelum dipindah ke History.',
                ];
            }

            $lockedOrder->update([
                'order_status' => $targetStatus,
            ]);

            // If order completed, disable custom bouquets
            if ($targetStatus === 'completed') {
                $lockedOrder->orderDetails()
                    ->where('item_type', 'bouquet')
                    ->whereNotNull('bouquet_unit_id')
                    ->get()
                    ->each(function (OrderDetail $detail) {
                        $unit = $detail->bouquetUnit;
                        if ($unit && $unit->type && $unit->type->is_custom) {
                            $unit->update(['is_active' => false]);
                        }
                    });
            }

            activity('orders')
                ->causedBy($request->user())
                ->performedOn($lockedOrder)
                ->event('status_updated')
                ->withProperties([
                    'old_status' => $currentStatus,
                    'new_status' => $targetStatus,
                    'shipping_type' => $shippingType,
                ])
                ->log('order.status_updated');

            return [
                'ok' => true,
                'changed' => true,
            ];
        });

        if (! ($result['ok'] ?? false)) {
            if (! empty($result['error_message'])) {
                return redirect()->back()
                    ->withErrors([
                        'order_status' => (string) $result['error_message'],
                    ]);
            }

            $allowed = $result['allowed_labels'] ?? [];
            $allowedText = empty($allowed) ? 'Tidak ada transisi lanjutan.' : implode(' atau ', $allowed);

            return redirect()->back()
                ->withErrors([
                    'order_status' => "Transisi status tidak valid. Lanjutkan sesuai urutan: {$allowedText}",
                ]);
        }

        return redirect()->back()
            ->with('success', ($result['changed'] ?? false) ? 'Status order berhasil diperbarui.' : 'Status order tidak berubah.');
    }

    public function updatePaymentStatus(Request $request, Order $order): RedirectResponse
    {
        abort_unless($request->user()?->can('orders.status.update'), 403);

        $validated = $request->validate([
            'payment_status' => ['required', Rule::in(['unpaid', 'dp', 'paid'])],
        ]);

        $targetStatus = (string) $validated['payment_status'];
        $result = DB::transaction(function () use ($order, $targetStatus, $request): array {
            $lockedOrder = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            $currentStatus = (string) $lockedOrder->payment_status;
            $orderStatus = (string) $lockedOrder->order_status;
            $downPayment = (float) ($lockedOrder->down_payment ?? 0);
            $total = (float) ($lockedOrder->total ?? 0);

            if ($currentStatus === $targetStatus) {
                return [
                    'ok' => true,
                    'changed' => false,
                ];
            }

            if ($targetStatus === 'dp' && ($downPayment <= 0 || $downPayment >= $total)) {
                return [
                    'ok' => false,
                    'error_message' => 'Status DP hanya bisa dipakai jika DP lebih dari 0 dan kurang dari total order.',
                ];
            }

            if ($orderStatus === 'completed' && $targetStatus !== 'paid') {
                return [
                    'ok' => false,
                    'error_message' => 'Order history yang sudah selesai hanya boleh berstatus pembayaran Lunas.',
                ];
            }

            $lockedOrder->update([
                'payment_status' => $targetStatus,
            ]);

            activity('orders')
                ->causedBy($request->user())
                ->performedOn($lockedOrder)
                ->event('payment_status_updated')
                ->withProperties([
                    'old_status' => $currentStatus,
                    'new_status' => $targetStatus,
                ])
                ->log('order.payment_status_updated');

            return [
                'ok' => true,
                'changed' => true,
            ];
        });

        if (! ($result['ok'] ?? false)) {
            return redirect()->back()
                ->withErrors([
                    'payment_status' => (string) ($result['error_message'] ?? 'Status pembayaran tidak valid.'),
                ]);
        }

        return redirect()->back()
            ->with('success', ($result['changed'] ?? false) ? 'Status pembayaran berhasil diperbarui.' : 'Status pembayaran tidak berubah.');
    }

    // ─── Order Detail Methods ─────────────────────────────────────────────────

    public function storeDetail(Request $request, Order $order): RedirectResponse
    {
        abort_unless($request->user()?->can('orders.create'), 403);

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

        DB::transaction(function () use ($order, $validated, $subtotal): void {
            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedOrder->orderDetails()->create([...$validated, 'subtotal' => $subtotal]);

            $itemsSubtotal = (float) $lockedOrder->orderDetails()->sum('subtotal');
            if ((float) ($lockedOrder->down_payment ?? 0) > $itemsSubtotal) {
                throw ValidationException::withMessages([
                    'down_payment' => 'Down payment tidak boleh lebih besar dari subtotal item.',
                ]);
            }

            $lockedOrder->update([
                'total' => round($itemsSubtotal + (float) ($lockedOrder->shipping_fee ?? 0), 2),
            ]);
        });

        return redirect()->route('orders.show', $order)
            ->with('success', 'Item berhasil ditambahkan ke order.');
    }

    public function destroyDetail(Request $request, Order $order, OrderDetail $orderDetail): RedirectResponse
    {
        abort_unless($request->user()?->can('orders.delete'), 403);

        DB::transaction(function () use ($order, $orderDetail): void {
            /** @var Order $lockedOrder */
            $lockedOrder = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedDetail = OrderDetail::query()
                ->whereKey($orderDetail->id)
                ->where('order_id', $lockedOrder->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedDetail->delete();

            $itemsSubtotal = (float) $lockedOrder->orderDetails()->sum('subtotal');
            if ((float) ($lockedOrder->down_payment ?? 0) > $itemsSubtotal) {
                throw ValidationException::withMessages([
                    'down_payment' => 'Down payment tidak boleh lebih besar dari subtotal item.',
                ]);
            }

            $lockedOrder->update([
                'total' => round($itemsSubtotal + (float) ($lockedOrder->shipping_fee ?? 0), 2),
            ]);
        });

        return redirect()->route('orders.show', $order)
            ->with('success', 'Item berhasil dihapus dari order.');
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    private function resolveSubtotal(array $detail): float
    {
        if ($detail['item_type'] === 'bouquet') {
            $unit = BouquetUnit::findOrFail($detail['bouquet_unit_id']);
            $price = $unit->price;
            $money = $detail['money_bouquet'] ?? 0;

            return (float) ($price + $money) * $detail['quantity'];
        }

        $unit = ItemUnit::findOrFail($detail['inventory_item_id']);

        return (float) $unit->price * $detail['quantity'];
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
