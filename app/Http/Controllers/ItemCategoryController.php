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
        $sortBy = $request->input('sort_by', 'name');
        $sortDir = $request->input('sort_dir', 'asc');

        $categories = ItemCategory::query()
            ->withCount('itemUnits')
            ->when($request->search, fn ($q) => $q->where('name', 'like', "%{$request->search}%"))
            ->when($request->boolean('trashed'), fn ($q) => $q->onlyTrashed())
            ->orderBy($sortBy, $sortDir)
            ->paginate(20)
            ->withQueryString();

        $categoryOptions = ItemCategory::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('ItemUnits/Index', [
            'categories' => $categories,
            'categoryOptions' => $categoryOptions,
            'filters' => $request->only('search', 'trashed', 'sort_by', 'sort_dir'),
            'tab' => 'categories',
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

    public function restore(int $id): RedirectResponse
    {
        ItemCategory::withTrashed()->findOrFail($id)->restore();

        return redirect()->route('item-categories.index')
            ->with('success', 'Kategori item berhasil dipulihkan.');
    }

    public function forceDelete(int $id): RedirectResponse
    {
        ItemCategory::withTrashed()->findOrFail($id)->forceDelete();

        return redirect()->route('item-categories.index')
            ->with('success', 'Kategori item berhasil dihapus permanen.');
    }
}
