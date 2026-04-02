<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreBouquetCategoryRequest;
use App\Http\Requests\UpdateBouquetCategoryRequest;
use App\Models\BouquetCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BouquetCategoryController extends Controller
{
    public function index(Request $request): Response
    {
        [$sortBy, $sortDir] = $this->resolveSort(
            $request,
            ['name', 'slug', 'bouquet_types_count', 'created_at', 'updated_at'],
            'name',
            'asc',
        );

        $categories = BouquetCategory::query()
            ->when($request->search, fn ($q) => $q->where('name', 'like', "%{$request->search}%"))
            ->when($request->boolean('trashed'), fn ($q) => $q->onlyTrashed())
            ->withCount('bouquetTypes')
            ->orderBy($sortBy, $sortDir)
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Bouquets/Index', [
            'categories' => $categories,
            'filters' => $request->only('search', 'trashed', 'sort_by', 'sort_dir'),
            'tab' => 'categories',
        ]);
    }

    public function store(StoreBouquetCategoryRequest $request): RedirectResponse
    {
        \Illuminate\Support\Facades\DB::transaction(function () use ($request) {
            $category = \App\Models\BouquetCategory::create($request->validated());

            // Create default custom type for this category
            $category->bouquetTypes()->create([
                'name' => 'Custom',
                'description' => 'Tipe kustom untuk kategori ' . $category->name,
                'is_custom' => true,
            ]);
        });

        return redirect()->back()->with('success', 'Kategori bouquet berhasil ditambahkan beserta tipe kustom bawaan.');
    }

    public function update(UpdateBouquetCategoryRequest $request, BouquetCategory $bouquetCategory): RedirectResponse
    {
        $bouquetCategory->update($request->validated());

        return redirect()->back()->with('success', 'Kategori bouquet berhasil diperbarui.');
    }

    public function destroy(BouquetCategory $bouquetCategory): RedirectResponse
    {
        $bouquetCategory->delete();

        return redirect()->back()->with('success', 'Kategori bouquet berhasil dihapus secara temporer.');
    }

    public function restore(int $id): RedirectResponse
    {
        BouquetCategory::withTrashed()->findOrFail($id)->restore();

        return redirect()->back()->with('success', 'Kategori bouquet berhasil dipulihkan.');
    }

    public function forceDelete(int $id): RedirectResponse
    {
        BouquetCategory::withTrashed()->findOrFail($id)->forceDelete();

        return redirect()->back()->with('success', 'Kategori bouquet berhasil dihapus permanen.');
    }
}
