<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateStockMovementAction;
use App\Http\Requests\StoreStockMovementRequest;
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
        $movements = StockMovement::with('item')
            ->when($request->item_id, fn ($q) => $q->where('item_id', $request->item_id))
            ->when($request->type, fn ($q) => $q->where('type', $request->type))
            ->when($request->date_from, fn ($q) => $q->whereDate('created_at', '>=', $request->date_from))
            ->when($request->date_to, fn ($q) => $q->whereDate('created_at', '<=', $request->date_to))
            ->orderByDesc('created_at')
            ->paginate(30)
            ->withQueryString();

        $items = ItemUnit::orderBy('name')->get(['id', 'name', 'serial_number']);

        return Inertia::render('StockMovements/Index', [
            'movements' => $movements,
            'items' => $items,
            'filters' => $request->only('item_id', 'type', 'date_from', 'date_to'),
        ]);
    }

    public function store(StoreStockMovementRequest $request, CreateStockMovementAction $action): RedirectResponse
    {
        $action->handle($request);

        return redirect()->route('stock-movements.index')
            ->with('success', 'Pergerakan stok berhasil dicatat.');
    }
}
