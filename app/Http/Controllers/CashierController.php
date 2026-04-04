<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateOrderAction;
use App\Http\Requests\StoreOrderRequest;
use App\Models\BouquetCategory;
use App\Models\BouquetUnit;
use App\Models\Customer;
use App\Models\Delivery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CashierController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Orders/Index', $this->cashierPayload($request));
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
                    ->orWhere('phone_number', 'like', $contains);
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

        $action->handle($request);

        return redirect()->route('cashier.index')
            ->with('success', 'Order berhasil dibuat.');
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
            ->paginate(12, ['*'], 'catalog_page')
            ->withQueryString();

        return [
            'customers' => [],
            'bouquetUnits' => $bouquetUnits,
            'bouquetCategories' => BouquetCategory::query()
                ->orderBy('name')
                ->get(['id', 'name']),
            'catalogFilters' => [
                'search' => $catalogSearch,
                'category_id' => $catalogCategoryId,
            ],
            'deliveryReferences' => [],
            'canCustomBouquet' => (bool) $request->user()?->can('input custom bouquet'),
        ];
    }
}
