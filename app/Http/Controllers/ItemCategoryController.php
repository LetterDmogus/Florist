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
        $perPage = $this->resolvePerPage($request);
        [$sortBy, $sortDir] = $this->resolveSort(
            $request,
            ['name', 'slug', 'item_units_count', 'created_at', 'updated_at'],
            'name',
            'asc',
        );

        $categories = ItemCategory::query()
            ->withCount('itemUnits')
            ->when($request->search, fn ($q) => $q->where('name', 'like', "%{$request->search}%"))
            ->when($request->boolean('trashed'), fn ($q) => $q->onlyTrashed())
            ->orderBy($sortBy, $sortDir)
            ->paginate($perPage)
            ->withQueryString();

        $categoryOptions = ItemCategory::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('ItemUnits/Index', [
            'categories' => $categories,
            'categoryOptions' => $categoryOptions,
            'filters' => [
                ...$request->only('search', 'trashed', 'sort_by', 'sort_dir'),
                'per_page' => $perPage,
            ],
            'tab' => 'categories',
        ]);
    }

    public function show(Request $request, ItemCategory $item_category): Response|\Illuminate\Http\JsonResponse
    {
        if ($request->wantsJson()) {
            return response()->json([
                'item' => $item_category,
                'audit_trail' => $item_category->getAuditTrail(),
            ]);
        }

        return Inertia::render('ItemUnits/Show', [
            'item' => $item_category,
            'audit_trail' => $item_category->getAuditTrail(),
            'tab' => 'categories',
        ]);
    }

    public function store(StoreItemCategoryRequest $request): RedirectResponse
    {
        ItemCategory::create($request->validated());

        return redirect()->route('item-categories.index')
            ->with('success', 'Kategori item berhasil ditambahkan.');
    }

    public function update(UpdateItemCategoryRequest $request, ItemCategory $item_category): RedirectResponse
    {
        $item_category->update($request->validated());

        return redirect()->route('item-categories.index')
            ->with('success', 'Kategori item berhasil diperbarui.');
    }

    public function destroy(ItemCategory $item_category): RedirectResponse
    {
        $item_category->delete();

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
