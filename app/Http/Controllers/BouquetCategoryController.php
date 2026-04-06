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
        $perPage = $this->resolvePerPage($request);
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
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('Bouquets/Index', [
            'categories' => $categories,
            'filters' => [
                ...$request->only('search', 'trashed', 'sort_by', 'sort_dir'),
                'per_page' => $perPage,
            ],
            'tab' => 'categories',
        ]);
    }

    public function show(Request $request, BouquetCategory $bouquet_category): Response|\Illuminate\Http\JsonResponse
    {
        if ($request->wantsJson()) {
            return response()->json([
                'item' => $bouquet_category,
                'audit_trail' => $bouquet_category->getAuditTrail(),
            ]);
        }

        return Inertia::render('Bouquets/Index', [
            'item' => $bouquet_category,
            'audit_trail' => $bouquet_category->getAuditTrail(),
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

    public function update(UpdateBouquetCategoryRequest $request, BouquetCategory $bouquet_category): RedirectResponse
    {
        $bouquet_category->update($request->validated());

        return redirect()->back()->with('success', 'Kategori bouquet berhasil diperbarui.');
    }

    public function destroy(BouquetCategory $bouquet_category): RedirectResponse
    {
        $bouquet_category->delete();

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
