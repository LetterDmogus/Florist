<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreItemUnitRequest;
use App\Http\Requests\UpdateItemUnitRequest;
use App\Models\ItemCategory;
use App\Models\ItemUnit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ItemUnitController extends Controller
{
    public function index(Request $request): Response
    {
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');

        $items = ItemUnit::query()
            ->with('category')
            ->when($request->search, fn ($q) => $q->where('name', 'like', "%{$request->search}%")
                ->orWhere('serial_number', 'like', "%{$request->search}%"))
            ->when($request->category_id, fn ($q) => $q->where('category_id', $request->category_id))
            ->when($request->boolean('trashed'), fn ($q) => $q->onlyTrashed())
            ->orderBy($sortBy, $sortDir)
            ->paginate(20)
            ->withQueryString();

        $categories = ItemCategory::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('ItemUnits/Index', [
            'items' => $items,
            'categoryOptions' => $categories,
            'filters' => $request->only('search', 'category_id', 'trashed', 'sort_by', 'sort_dir'),
            'tab' => 'units',
        ]);
    }

    public function show(ItemUnit $itemUnit): Response
    {
        $itemUnit->load(['category', 'stockMovements' => fn ($q) => $q->latest()->limit(20)]);

        return Inertia::render('ItemUnits/Show', [
            'item' => $itemUnit,
        ]);
    }

    public function store(StoreItemUnitRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $unit = ItemUnit::create($validated);

        if ($request->hasFile('image')) {
            $unit->addMediaFromRequest('image')->toMediaCollection('images');
        }

        return redirect()->route('item-units.index')
            ->with('success', 'Item berhasil ditambahkan.');
    }

    public function update(UpdateItemUnitRequest $request, ItemUnit $itemUnit): RedirectResponse
    {
        $validated = $request->validated();
        $itemUnit->update($validated);

        if ($request->hasFile('image')) {
            $itemUnit->clearMediaCollection('images');
            $itemUnit->addMediaFromRequest('image')->toMediaCollection('images');
        }

        return redirect()->route('item-units.index')
            ->with('success', 'Item berhasil diperbarui.');
    }

    public function destroy(ItemUnit $itemUnit): RedirectResponse
    {
        $itemUnit->delete();

        return redirect()->route('item-units.index')
            ->with('success', 'Item berhasil dihapus.');
    }

    public function restore(int $id): RedirectResponse
    {
        ItemUnit::withTrashed()->findOrFail($id)->restore();

        return redirect()->route('item-units.index')
            ->with('success', 'Item berhasil dipulihkan.');
    }

    public function forceDelete(int $id): RedirectResponse
    {
        $itemUnit = ItemUnit::withTrashed()->findOrFail($id);
        $itemUnit->clearMediaCollection('images');
        $itemUnit->forceDelete();

        return redirect()->route('item-units.index')
            ->with('success', 'Item berhasil dihapus permanen.');
    }
}
