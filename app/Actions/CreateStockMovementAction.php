<?php

declare(strict_types=1);

namespace App\Actions;

use App\Http\Requests\StoreStockMovementRequest;
use App\Models\ItemUnit;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

class CreateStockMovementAction
{
    public function handle(StoreStockMovementRequest $request): StockMovement
    {
        return DB::transaction(function () use ($request): StockMovement {
            $validated = $request->validated();

            /** @var ItemUnit $item */
            $item = ItemUnit::findOrFail($validated['item_id']);

            // Hitung total jika belum diisi
            $validated['total'] = $validated['price_at_the_time'] * $validated['quantity'];

            $movement = StockMovement::create($validated);

            // Update stok berdasarkan tipe gerakan
            match ($validated['type']) {
                'in' => $item->increment('stock', $validated['quantity']),
                'out', 'damaged', 'sold' => $item->decrement('stock', $validated['quantity']),
            };

            return $movement;
        });
    }
}
