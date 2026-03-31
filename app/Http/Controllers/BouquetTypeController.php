<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreBouquetTypeRequest;
use App\Http\Requests\UpdateBouquetTypeRequest;
use App\Models\BouquetCategory;
use App\Models\BouquetType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BouquetTypeController extends Controller
{
    public function index(Request $request): Response
    {
        $types = BouquetType::with('category')
            ->when($request->search, fn ($q) => $q->where('name', 'like', "%{$request->search}%"))
            ->when($request->category_id, fn ($q) => $q->where('category_id', $request->category_id))
            ->when($request->has('is_custom'), fn ($q) => $q->where('is_custom', $request->boolean('is_custom')))
            ->when($request->boolean('trashed'), fn ($q) => $q->onlyTrashed())
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $categoryOptions = BouquetCategory::orderBy('name')->get(['id', 'name']);

        return Inertia::render('Bouquets/Index', [
            'types' => $types,
            'categoryOptions' => $categoryOptions,
            'filters' => $request->only('search', 'category_id', 'is_custom', 'trashed'),
            'tab' => 'types',
        ]);
    }

    public function store(StoreBouquetTypeRequest $request): RedirectResponse
    {
        BouquetType::create($request->validated());

        return redirect()->back()->with('success', 'Tipe bouquet berhasil ditambahkan.');
    }

    public function update(UpdateBouquetTypeRequest $request, BouquetType $bouquetType): RedirectResponse
    {
        $bouquetType->update($request->validated());

        return redirect()->back()->with('success', 'Tipe bouquet berhasil diperbarui.');
    }

    public function destroy(BouquetType $bouquetType): RedirectResponse
    {
        if ($bouquetType->is_custom) {
            return redirect()->back()->with('error', 'Tipe kustom bawaan tidak dapat dihapus.');
        }

        $bouquetType->delete();

        return redirect()->back()->with('success', 'Tipe bouquet berhasil dihapus secara temporer.');
    }

    public function restore(int $id): RedirectResponse
    {
        BouquetType::withTrashed()->findOrFail($id)->restore();

        return redirect()->back()->with('success', 'Tipe bouquet berhasil dipulihkan.');
    }

    public function forceDelete(int $id): RedirectResponse
    {
        BouquetType::withTrashed()->findOrFail($id)->forceDelete();

        return redirect()->back()->with('success', 'Tipe bouquet berhasil dihapus permanen.');
    }
}
