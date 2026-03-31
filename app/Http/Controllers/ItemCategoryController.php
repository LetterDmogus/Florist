<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreItemCategoryRequest;
use App\Http\Requests\UpdateItemCategoryRequest;
use App\Models\ItemCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ItemCategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $categories = ItemCategory::query()
            ->withCount('itemUnits')
            ->when($request->search, fn ($q) => $q->where('name', 'like', "%{$request->search}%"))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('ItemCategories/Index', [
            'categories' => $categories,
            'filters' => $request->only('search'),
        ]);
    }

    public function store(StoreItemCategoryRequest $request): RedirectResponse
    {
        ItemCategory::create($request->validated());

        return redirect()->route('item-categories.index')
            ->with('success', 'Kategori item berhasil ditambahkan.');
    }

    public function update(UpdateItemCategoryRequest $request, ItemCategory $itemCategory): RedirectResponse
    {
        $itemCategory->update($request->validated());

        return redirect()->route('item-categories.index')
            ->with('success', 'Kategori item berhasil diperbarui.');
    }

    public function destroy(ItemCategory $itemCategory): RedirectResponse
    {
        $itemCategory->delete();

        return redirect()->route('item-categories.index')
            ->with('success', 'Kategori item berhasil dihapus.');
    }
}
