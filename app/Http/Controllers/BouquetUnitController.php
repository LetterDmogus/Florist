<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreBouquetUnitRequest;
use App\Http\Requests\UpdateBouquetUnitRequest;
use App\Models\BouquetType;
use App\Models\BouquetUnit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BouquetUnitController extends Controller
{
    public function index(Request $request): Response
    {
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDir = $request->input('sort_dir', 'desc');

        $units = BouquetUnit::with('type.category')
            ->when($request->search, fn ($q) => $q->where('name', 'like', "%{$request->search}%")
                ->orWhere('serial_number', 'like', "%{$request->search}%"))
            ->when($request->type_id, fn ($q) => $q->where('type_id', $request->type_id))
            ->when($request->boolean('trashed'), fn ($q) => $q->onlyTrashed())
            ->orderBy($sortBy, $sortDir)
            ->paginate(20)
            ->withQueryString();

        $typeOptions = BouquetType::with('category')->orderBy('name')->get(['id', 'name', 'category_id']);

        return Inertia::render('Bouquets/Index', [
            'units' => $units,
            'typeOptions' => $typeOptions,
            'filters' => $request->only('search', 'type_id', 'trashed', 'sort_by', 'sort_dir'),
            'tab' => 'units',
        ]);
    }

    public function show(BouquetUnit $bouquetUnit): Response
    {
        $bouquetUnit->load('type.category');

        return Inertia::render('BouquetUnits/Show', [
            'unit' => $bouquetUnit,
        ]);
    }

    public function store(StoreBouquetUnitRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $unit = BouquetUnit::create($validated);

        if ($request->hasFile('image')) {
            $unit->addMediaFromRequest('image')->toMediaCollection('images');
        }

        return redirect()->back()->with('success', 'Bouquet berhasil ditambahkan.');
    }

    public function update(UpdateBouquetUnitRequest $request, BouquetUnit $bouquetUnit): RedirectResponse
    {
        $validated = $request->validated();
        $bouquetUnit->update($validated);

        if ($request->hasFile('image')) {
            $bouquetUnit->clearMediaCollection('images');
            $bouquetUnit->addMediaFromRequest('image')->toMediaCollection('images');
        }

        return redirect()->back()->with('success', 'Bouquet berhasil diperbarui.');
    }

    public function destroy(BouquetUnit $bouquetUnit): RedirectResponse
    {
        $bouquetUnit->delete();

        return redirect()->back()->with('success', 'Bouquet berhasil dihapus secara temporer.');
    }

    public function restore(int $id): RedirectResponse
    {
        BouquetUnit::withTrashed()->findOrFail($id)->restore();

        return redirect()->back()->with('success', 'Bouquet berhasil dipulihkan.');
    }

    public function forceDelete(int $id): RedirectResponse
    {
        BouquetUnit::withTrashed()->findOrFail($id)->forceDelete();

        return redirect()->back()->with('success', 'Bouquet berhasil dihapus permanen.');
    }
}
