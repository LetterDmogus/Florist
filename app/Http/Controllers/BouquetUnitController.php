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
        $perPage = $this->resolvePerPage($request);
        [$sortBy, $sortDir] = $this->resolveSort(
            $request,
            ['serial_number', 'name', 'price', 'type_id', 'created_at', 'updated_at'],
            'created_at',
            'desc',
        );

        $activeStatus = $request->input('active_status', 'active'); // default active
        $itemType = $request->input('item_type', 'catalog'); // default catalog (non-custom)

        $units = BouquetUnit::with(['type.category', 'media'])
            ->when($request->search, fn ($q) => $q->where('name', 'like', "%{$request->search}%")
                ->orWhere('serial_number', 'like', "%{$request->search}%"))
            ->when($request->type_id, fn ($q) => $q->where('type_id', $request->type_id))
            // Filter is_active
            ->when($activeStatus === 'active', fn ($q) => $q->where('is_active', true))
            ->when($activeStatus === 'inactive', fn ($q) => $q->where('is_active', false))
            // Filter item_type
            ->when($itemType === 'custom', fn ($q) => $q->whereHas('type', fn ($t) => $t->where('is_custom', true)))
            ->when($itemType === 'catalog', fn ($q) => $q->whereHas('type', fn ($t) => $t->where('is_custom', false)))
            // Filter trashed
            ->when($request->boolean('trashed'), fn ($q) => $q->onlyTrashed())
            ->orderBy($sortBy, $sortDir)
            ->paginate($perPage)
            ->withQueryString();

        $typeOptions = BouquetType::with('category')
            ->where('is_custom', false)
            ->orderBy('name')
            ->get(['id', 'name', 'category_id']);

        return Inertia::render('Bouquets/Index', [
            'units' => $units,
            'typeOptions' => $typeOptions,
            'filters' => array_merge(
                ['active_status' => $activeStatus, 'item_type' => $itemType],
                $request->only('search', 'type_id', 'trashed', 'sort_by', 'sort_dir', 'per_page')
            ),
            'tab' => 'units',
        ]);
    }

    public function show(Request $request, BouquetUnit $bouquet_unit): Response|\Illuminate\Http\JsonResponse
    {
        if ($request->wantsJson()) {
            return response()->json([
                'item' => $bouquet_unit,
                'audit_trail' => $bouquet_unit->getAuditTrail(),
            ]);
        }

        $bouquet_unit->load(['type.category', 'media']);

        return Inertia::render('BouquetUnits/Show', [
            'unit' => $bouquet_unit,
            'audit_trail' => $bouquet_unit->getAuditTrail(),
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

    public function update(UpdateBouquetUnitRequest $request, BouquetUnit $bouquet_unit): RedirectResponse
    {
        $validated = $request->validated();
        $bouquet_unit->update($validated);

        if ($request->hasFile('image')) {
            $bouquet_unit->clearMediaCollection('images');
            $bouquet_unit->addMediaFromRequest('image')->toMediaCollection('images');
        }

        return redirect()->back()->with('success', 'Bouquet berhasil diperbarui.');
    }

    public function destroy(BouquetUnit $bouquet_unit): RedirectResponse
    {
        $bouquet_unit->delete();

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
