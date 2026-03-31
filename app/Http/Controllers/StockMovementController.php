<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateStockMovementAction;
use App\Http\Requests\StoreStockMovementRequest;
use App\Models\Customer;
use App\Models\ItemUnit;
use App\Models\StockMovement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockMovementController extends Controller
{
    public function index(Request $request): Response
    {
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');

        $movements = StockMovement::query()
            ->with(['item', 'order.customer'])
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
            ->withQueryString();

        $items = ItemUnit::query()
            ->orderBy('name')
            ->get(['id', 'name', 'serial_number', 'stock', 'price']);

        $customers = Customer::query()
            ->orderBy('name')
            ->get(['id', 'name', 'phone_number']);

        return Inertia::render('StockMovements/Index', [
            'movements' => $movements,
            'items' => $items,
            'customers' => $customers,
            'filters' => $request->only('search', 'item_id', 'type', 'date_from', 'date_to', 'sort_by', 'sort_dir'),
            'typeOptions' => collect(StockMovement::TYPE_LABELS)
                ->map(fn (string $label, string $value): array => [
                    'value' => $value,
                    'label' => $label,
                ])
                ->values()
                ->all(),
            'canCreateStockMovement' => $request->user()?->hasAnyRole(['super-admin', 'admin']) ?? false,
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
