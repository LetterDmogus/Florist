<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\ParseInventorySupplyReportAction;
use App\Http\Requests\ImportItemUnitRequest;
use App\Http\Requests\StoreItemUnitRequest;
use App\Http\Requests\UpdateItemUnitRequest;
use App\Models\ItemCategory;
use App\Models\ItemUnit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class ItemUnitController extends Controller
{
    public function index(Request $request): Response
    {
        [$sortBy, $sortDir] = $this->resolveSort(
            $request,
            ['serial_number', 'name', 'price', 'individual', 'stock', 'category_id', 'created_at', 'updated_at'],
            'created_at',
            'desc',
        );

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
            'importPreview' => $request->session()->get('item_unit_import_preview'),
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

    public function importPreview(
        ImportItemUnitRequest $request,
        ParseInventorySupplyReportAction $parseInventorySupplyReport,
    ): RedirectResponse {
        $validated = $request->validated();
        $file = $request->file('file');
        $categoryId = (int) $validated['category_id'];

        if ($file === null) {
            return redirect()->route('item-units.index')
                ->with('error', 'File import tidak ditemukan.');
        }

        try {
            $rows = $parseInventorySupplyReport->handle($file->getRealPath());
        } catch (Throwable $exception) {
            report($exception);

            return redirect()->route('item-units.index')
                ->with('error', 'Gagal membaca file import. Pastikan format file sesuai template.');
        }

        if ($rows === []) {
            return redirect()->route('item-units.index')
                ->with('error', 'Tidak ada data inventory yang dapat diimpor dari file.');
        }

        $rows = $this->normalizeRowsBySerial($rows);
        $previewStats = $this->estimateImportStats($rows);
        $token = (string) Str::uuid();
        $expiresAt = now()->addMinutes(30);

        $this->clearImportPreview($request);

        Cache::put(
            $this->importPreviewCacheKey($token),
            [
                'rows' => $rows,
                'category_id' => $categoryId,
            ],
            $expiresAt,
        );

        $categoryName = ItemCategory::query()->whereKey($categoryId)->value('name');

        $request->session()->put('item_unit_import_preview', [
            'token' => $token,
            'file_name' => $file->getClientOriginalName(),
            'category_id' => $categoryId,
            'category_name' => $categoryName,
            'total_rows' => count($rows),
            'estimated_create' => $previewStats['create'],
            'estimated_update' => $previewStats['update'],
            'sample_rows' => array_slice($rows, 0, 15),
            'expires_at' => $expiresAt->toIso8601String(),
        ]);

        return redirect()->route('item-units.index')
            ->with('success', 'Preview import siap. Periksa data lalu konfirmasi import.');
    }

    public function importCommit(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->hasAnyRole(['super-admin', 'admin']), 403);

        $validated = $request->validate([
            'token' => ['required', 'uuid'],
        ]);

        $preview = $request->session()->get('item_unit_import_preview');
        if (! is_array($preview) || ($preview['token'] ?? null) !== $validated['token']) {
            return redirect()->route('item-units.index')
                ->with('error', 'Preview import tidak valid atau sudah kedaluwarsa.');
        }

        $cachePayload = Cache::pull($this->importPreviewCacheKey($validated['token']));
        if (! is_array($cachePayload)) {
            $request->session()->forget('item_unit_import_preview');

            return redirect()->route('item-units.index')
                ->with('error', 'Sesi preview import kedaluwarsa. Silakan upload ulang file.');
        }

        $rows = $cachePayload['rows'] ?? [];
        $categoryId = (int) ($cachePayload['category_id'] ?? 0);

        if (! is_array($rows) || $rows === [] || $categoryId <= 0) {
            $request->session()->forget('item_unit_import_preview');

            return redirect()->route('item-units.index')
                ->with('error', 'Data preview import tidak lengkap. Silakan upload ulang file.');
        }

        [$created, $updated] = $this->applyImportRows($rows, $categoryId);
        $request->session()->forget('item_unit_import_preview');

        activity('inventory')
            ->causedBy($request->user())
            ->event('import_committed')
            ->withProperties([
                'category_id' => $categoryId,
                'total_rows' => count($rows),
                'created_count' => $created,
                'updated_count' => $updated,
            ])
            ->log('item_unit.import_committed');

        return redirect()->route('item-units.index')
            ->with('success', "Import inventory selesai. {$created} data baru, {$updated} data diperbarui.");
    }

    public function importDiscard(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->hasAnyRole(['super-admin', 'admin']), 403);

        $validated = $request->validate([
            'token' => ['nullable', 'uuid'],
        ]);

        $preview = $request->session()->get('item_unit_import_preview');
        $previewToken = is_array($preview) ? ($preview['token'] ?? null) : null;
        $requestedToken = $validated['token'] ?? null;

        if (is_string($previewToken)) {
            if ($requestedToken === null || $requestedToken === $previewToken) {
                Cache::forget($this->importPreviewCacheKey($previewToken));
            }
        }

        $request->session()->forget('item_unit_import_preview');

        return redirect()->route('item-units.index')
            ->with('success', 'Preview import dibatalkan.');
    }

    private function clearImportPreview(Request $request): void
    {
        $preview = $request->session()->get('item_unit_import_preview');
        $token = is_array($preview) ? ($preview['token'] ?? null) : null;
        if (is_string($token)) {
            Cache::forget($this->importPreviewCacheKey($token));
        }

        $request->session()->forget('item_unit_import_preview');
    }

    private function importPreviewCacheKey(string $token): string
    {
        return "item-unit-import-preview:{$token}";
    }

    private function normalizeRowsBySerial(array $rows): array
    {
        $normalized = [];

        foreach ($rows as $row) {
            if (! is_array($row) || empty($row['serial_number'])) {
                continue;
            }

            $normalized[(string) $row['serial_number']] = $row;
        }

        return array_values($normalized);
    }

    private function estimateImportStats(array $rows): array
    {
        $serials = collect($rows)
            ->pluck('serial_number')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($serials === []) {
            return ['create' => 0, 'update' => 0];
        }

        $existingCount = ItemUnit::withTrashed()
            ->whereIn('serial_number', $serials)
            ->count();

        $total = count($serials);

        return [
            'create' => max(0, $total - $existingCount),
            'update' => min($total, $existingCount),
        ];
    }

    private function applyImportRows(array $rows, int $categoryId): array
    {
        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($rows, $categoryId, &$created, &$updated): void {
            foreach ($rows as $row) {
                $payload = [
                    'category_id' => $categoryId,
                    'serial_number' => $row['serial_number'],
                    'name' => $row['name'],
                    'price' => $row['price'],
                    'individual' => $row['individual'],
                    'description' => $row['description'],
                    'stock' => $row['stock'],
                ];

                $existing = ItemUnit::withTrashed()
                    ->where('serial_number', $row['serial_number'])
                    ->first();

                if ($existing) {
                    $existing->update($payload);
                    if ($existing->trashed()) {
                        $existing->restore();
                    }
                    $updated++;

                    continue;
                }

                ItemUnit::create($payload);
                $created++;
            }
        });

        return [$created, $updated];
    }
}
