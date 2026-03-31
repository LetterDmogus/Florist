<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exports\PurchaseReportExport;
use App\Exports\SalesReportExport;
use App\Http\Requests\StoreReportEntryRequest;
use App\Http\Requests\UpdateReportEntryRequest;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\ReportEntry;
use App\Models\StockMovement;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    public function index(Request $request): RedirectResponse
    {
        return redirect()->route('reports.sales.index', $request->only('month', 'year'));
    }

    public function salesIndex(Request $request): Response
    {
        [$year, $month, $start, $end] = $this->resolvePeriod($request);
        $data = $this->collectMonthlyData($start, $end);

        return Inertia::render('Reports/Sales', [
            'filters' => [
                'month' => $month,
                'year' => $year,
            ],
            'monthOptions' => $this->resolveMonthOptions($year),
            'yearOptions' => $this->resolveYearOptions(),
            'salesSummary' => $data['salesSummary'],
            'salesRows' => $data['salesRows']->values()->all(),
            'profitSummary' => $data['profitSummary'],
        ]);
    }

    public function purchasesIndex(Request $request): Response
    {
        [$year, $month, $start, $end] = $this->resolvePeriod($request);
        $data = $this->collectMonthlyData($start, $end);

        return Inertia::render('Reports/Purchases', [
            'filters' => [
                'month' => $month,
                'year' => $year,
            ],
            'monthOptions' => $this->resolveMonthOptions($year),
            'yearOptions' => $this->resolveYearOptions(),
            'purchaseSummary' => $data['purchaseSummary'],
            'purchaseRows' => $data['purchaseRows']->values()->all(),
            'supplyPurchaseRows' => $data['supplyPurchaseRows']->values()->all(),
            'storeExpenseRows' => $data['storeExpenseRows']->values()->all(),
            'rawMaterialRows' => $data['rawMaterialRows']->values()->all(),
            'shippingRows' => $data['shippingRows']->values()->all(),
            'refundRows' => $data['refundRows']->values()->all(),
            'reportEntries' => $data['reportEntries']->map(fn (ReportEntry $entry): array => [
                'id' => $entry->id,
                'occurred_on' => $entry->occurred_on?->format('Y-m-d'),
                'category' => $entry->category,
                'category_label' => ReportEntry::CATEGORY_LABELS[$entry->category] ?? $entry->category,
                'description' => $entry->description,
                'amount_idr' => (float) $entry->amount_idr,
                'amount_rmb' => (float) ($entry->amount_rmb ?? 0),
                'exchange_rate' => (float) ($entry->exchange_rate ?? 0),
                'freight_idr' => (float) ($entry->freight_idr ?? 0),
                'tracking_number' => $entry->tracking_number,
                'code' => $entry->code,
                'estimated_arrived_on' => $entry->estimated_arrived_on?->format('Y-m-d'),
                'notes' => $entry->notes,
            ])->values()->all(),
            'reportCategoryOptions' => collect(ReportEntry::CATEGORY_LABELS)
                ->map(fn (string $label, string $value): array => ['value' => $value, 'label' => $label])
                ->values()
                ->all(),
            'canManageReportEntries' => $request->user()?->hasAnyRole(['super-admin', 'admin']) ?? false,
        ]);
    }

    public function exportSales(Request $request): BinaryFileResponse
    {
        [$year, $month, $start, $end] = $this->resolvePeriod($request);
        $data = $this->collectMonthlyData($start, $end);

        $filename = sprintf('laporan-penjualan-%04d-%02d.xlsx', $year, $month);

        return Excel::download(new SalesReportExport(
            salesSummary: $data['salesSummary'],
            salesRows: $data['salesRows']->values()->all(),
            profitSummary: $data['profitSummary'],
            month: $month,
            year: $year,
        ), $filename);
    }

    public function exportPurchases(Request $request): BinaryFileResponse
    {
        [$year, $month, $start, $end] = $this->resolvePeriod($request);
        $data = $this->collectMonthlyData($start, $end);

        $filename = sprintf('laporan-pembelian-%04d-%02d.xlsx', $year, $month);

        return Excel::download(new PurchaseReportExport(
            purchaseSummary: $data['purchaseSummary'],
            purchaseRows: $data['purchaseRows']->values()->all(),
            supplyPurchaseRows: $data['supplyPurchaseRows']->values()->all(),
            storeExpenseRows: $data['storeExpenseRows']->values()->all(),
            rawMaterialRows: $data['rawMaterialRows']->values()->all(),
            shippingRows: $data['shippingRows']->values()->all(),
            refundRows: $data['refundRows']->values()->all(),
            month: $month,
            year: $year,
        ), $filename);
    }

    public function storeEntry(StoreReportEntryRequest $request): RedirectResponse
    {
        $payload = $request->validated();
        $payload['user_id'] = $request->user()->id;

        ReportEntry::create($payload);

        return redirect()->back()
            ->with('success', 'Data laporan manual berhasil ditambahkan.');
    }

    public function updateEntry(UpdateReportEntryRequest $request, ReportEntry $reportEntry): RedirectResponse
    {
        $reportEntry->update($request->validated());

        return redirect()->back()
            ->with('success', 'Data laporan manual berhasil diperbarui.');
    }

    public function destroyEntry(ReportEntry $reportEntry): RedirectResponse
    {
        $reportEntry->delete();

        return redirect()->back()
            ->with('success', 'Data laporan manual berhasil dihapus.');
    }

    private function resolvePeriod(Request $request): array
    {
        $year = max(2020, min(2100, (int) $request->integer('year', now()->year)));
        $month = max(1, min(12, (int) $request->integer('month', now()->month)));

        $start = CarbonImmutable::create($year, $month, 1)->startOfMonth();
        $end = $start->endOfMonth();

        return [$year, $month, $start, $end];
    }

    private function resolveMonthOptions(int $year): array
    {
        return collect(range(1, 12))
            ->map(fn (int $value): array => [
                'value' => $value,
                'label' => CarbonImmutable::create($year, $value, 1)->translatedFormat('F'),
            ])
            ->values()
            ->all();
    }

    private function collectMonthlyData(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $salesRows = $this->buildSalesRows($start, $end);
        $salesSummary = [
            'money' => round($salesRows->sum('money'), 2),
            'fee' => round($salesRows->sum('fee'), 2),
            'gosend' => round($salesRows->sum('gosend'), 2),
            'total' => round($salesRows->sum('total'), 2),
        ];

        $purchaseRows = $this->buildPurchaseRows($start, $end);
        $reportEntries = ReportEntry::query()
            ->whereBetween('occurred_on', [$start->toDateString(), $end->toDateString()])
            ->orderBy('occurred_on')
            ->orderBy('id')
            ->get();

        $entriesByCategory = $reportEntries->groupBy('category');

        $supplyIncomeTotal = $this->sumIdr($entriesByCategory->get('supply_income', collect()));
        $shippingRows = $this->buildGenericEntryRows($entriesByCategory->get('shipping_expense', collect()));
        $storeExpenseRows = $this->buildGenericEntryRows($entriesByCategory->get('store_expense', collect()));
        $rawMaterialRows = $this->buildGenericEntryRows($entriesByCategory->get('raw_material_expense', collect()));
        $profitAdjustments = $this->buildGenericEntryRows($entriesByCategory->get('profit_adjustment', collect()));
        $supplyPurchaseRows = $this->buildSupplyPurchaseRows($entriesByCategory->get('purchase_supply', collect()));
        $refundRows = $this->buildRefundRows($entriesByCategory->get('refund', collect()));

        $storeExpenseTotal = $this->sumGenericRows($storeExpenseRows);
        $rawMaterialExpenseTotal = $this->sumGenericRows($rawMaterialRows);
        $shippingTotal = $this->sumGenericRows($shippingRows);
        $purchaseTotal = round($purchaseRows->sum('total'), 2);
        $supplyPurchaseTotal = round($supplyPurchaseRows->sum('total'), 2);
        $refundIdrTotal = round($refundRows->sum('idr'), 2);
        $refundRmbTotal = round($refundRows->sum('rmb'), 2);

        $profitSummary = [
            'florist_income' => round($salesSummary['fee'], 2),
            'supply_income' => round($supplyIncomeTotal, 2),
            'purchase_total' => round($purchaseTotal, 2),
            'store_expense_total' => round($storeExpenseTotal, 2),
            'raw_material_expense_total' => round($rawMaterialExpenseTotal, 2),
            'shipping_total' => round($shippingTotal, 2),
        ];
        $profitSummary['gross_profit'] = round(
            $profitSummary['florist_income']
                + $profitSummary['supply_income']
                - $profitSummary['purchase_total']
                - $profitSummary['store_expense_total']
                - $profitSummary['raw_material_expense_total'],
            2
        );
        $profitSummary['adjustment_total'] = $this->sumGenericRows($profitAdjustments);
        $profitSummary['net_profit'] = round($profitSummary['gross_profit'] + $profitSummary['adjustment_total'], 2);

        $purchaseSummary = [
            'purchase_total' => $purchaseTotal,
            'supply_purchase_total' => $supplyPurchaseTotal,
            'store_expense_total' => round($storeExpenseTotal, 2),
            'raw_material_expense_total' => round($rawMaterialExpenseTotal, 2),
            'total_expense' => round($storeExpenseTotal + $rawMaterialExpenseTotal, 2),
            'refund_rmb_total' => $refundRmbTotal,
            'refund_idr_total' => $refundIdrTotal,
            'grand_total' => round(($storeExpenseTotal + $rawMaterialExpenseTotal) + $purchaseTotal - $refundIdrTotal, 2),
        ];

        return [
            'salesSummary' => $salesSummary,
            'salesRows' => $salesRows,
            'profitSummary' => $profitSummary,
            'purchaseSummary' => $purchaseSummary,
            'purchaseRows' => $purchaseRows,
            'supplyPurchaseRows' => $supplyPurchaseRows,
            'storeExpenseRows' => $storeExpenseRows,
            'rawMaterialRows' => $rawMaterialRows,
            'shippingRows' => $shippingRows,
            'refundRows' => $refundRows,
            'profitAdjustments' => $profitAdjustments,
            'reportEntries' => $reportEntries,
        ];
    }

    private function buildSalesRows(CarbonImmutable $start, CarbonImmutable $end): Collection
    {
        $details = OrderDetail::query()
            ->with([
                'order:id,shipping_date,shipping_time,deleted_at',
                'bouquetUnit:id,name,type_id',
                'bouquetUnit.type:id,name',
                'inventoryItem:id,name',
            ])
            ->whereHas('order', fn ($query) => $query
                ->whereNull('deleted_at')
                ->whereBetween('shipping_date', [$start->toDateString(), $end->toDateString()]))
            ->get()
            ->sortBy(fn (OrderDetail $detail) => sprintf(
                '%s|%s|%08d',
                $detail->order?->shipping_date?->format('Y-m-d') ?? '',
                (string) $detail->order?->shipping_time,
                $detail->id
            ))
            ->values();

        return $details->map(function (OrderDetail $detail, int $index): array {
            $model = $this->resolveModelLabel($detail);
            $isMoneyBouquet = $this->isMoneyBouquet($detail, $model);
            $money = $isMoneyBouquet ? (float) ($detail->money_bouquet ?? 0) : 0.0;
            $total = (float) $detail->subtotal;
            $fee = max(0, $total - $money);

            return [
                'no' => $index + 1,
                'date' => $detail->order?->shipping_date?->format('Y-m-d'),
                'model' => $model,
                'money' => round($money, 2),
                'fee' => round($fee, 2),
                'gosend' => 0.0,
                'total' => round($total, 2),
                'order_id' => $detail->order_id,
            ];
        });
    }

    private function buildPurchaseRows(CarbonImmutable $start, CarbonImmutable $end): Collection
    {
        return StockMovement::query()
            ->with('item:id,name')
            ->where('type', 'in')
            ->whereBetween('created_at', [$start->startOfDay(), $end->endOfDay()])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->map(function (StockMovement $movement, int $index): array {
                return [
                    'no' => $index + 1,
                    'date' => $movement->created_at?->format('Y-m-d'),
                    'item' => trim(implode(' - ', array_filter([
                        $movement->item?->name,
                        $movement->description,
                    ]))),
                    'rmb' => null,
                    'idr' => round((float) $movement->total, 2),
                    'freight' => null,
                    'tracking_number' => null,
                    'code' => null,
                    'estimate_arrived' => null,
                    'total' => round((float) $movement->total, 2),
                ];
            });
    }

    private function buildSupplyPurchaseRows(Collection $entries): Collection
    {
        return $entries->values()->map(function (ReportEntry $entry, int $index): array {
            return [
                'no' => $index + 1,
                'date' => $entry->occurred_on?->format('Y-m-d'),
                'item' => $entry->description,
                'rmb' => (float) ($entry->amount_rmb ?? 0),
                'idr' => round($entry->resolveAmountIdr(), 2),
                'freight' => round((float) ($entry->freight_idr ?? 0), 2),
                'tracking_number' => $entry->tracking_number,
                'code' => $entry->code,
                'estimate_arrived' => $entry->estimated_arrived_on?->format('Y-m-d'),
                'total' => round($entry->resolveTotalWithFreight(), 2),
            ];
        });
    }

    private function buildRefundRows(Collection $entries): Collection
    {
        return $entries->values()->map(function (ReportEntry $entry, int $index): array {
            return [
                'no' => $index + 1,
                'date' => $entry->occurred_on?->format('Y-m-d'),
                'description' => $entry->description,
                'rmb' => round((float) ($entry->amount_rmb ?? 0), 2),
                'idr' => round($entry->resolveAmountIdr(), 2),
                'rate' => round((float) ($entry->exchange_rate ?? 0), 2),
            ];
        });
    }

    private function buildGenericEntryRows(Collection $entries): Collection
    {
        return $entries->values()->map(function (ReportEntry $entry, int $index): array {
            return [
                'no' => $index + 1,
                'date' => $entry->occurred_on?->format('Y-m-d'),
                'description' => $entry->description,
                'amount' => round($entry->resolveAmountIdr(), 2),
            ];
        });
    }

    private function resolveModelLabel(OrderDetail $detail): string
    {
        if ($detail->item_type === 'bouquet') {
            $name = $detail->bouquetUnit?->type?->name ?? $detail->bouquetUnit?->name ?? 'BOUQUET';

            return Str::upper(trim($name));
        }

        return Str::upper((string) ($detail->inventoryItem?->name ?? 'INVENTORY'));
    }

    private function isMoneyBouquet(OrderDetail $detail, string $model): bool
    {
        if ($detail->item_type !== 'bouquet') {
            return false;
        }

        if (! $detail->money_bouquet) {
            return false;
        }

        return Str::contains(Str::lower($model), ['money', 'mb']);
    }

    private function sumIdr(Collection $entries): float
    {
        return round($entries->sum(fn (ReportEntry $entry): float => $entry->resolveAmountIdr()), 2);
    }

    private function sumGenericRows(Collection $rows): float
    {
        return round($rows->sum('amount'), 2);
    }

    private function resolveYearOptions(): array
    {
        $currentYear = now()->year;
        $datePool = collect([
            Order::query()->min('shipping_date'),
            Order::query()->max('shipping_date'),
            StockMovement::query()->min('created_at'),
            StockMovement::query()->max('created_at'),
            ReportEntry::query()->min('occurred_on'),
            ReportEntry::query()->max('occurred_on'),
        ])->filter();

        if ($datePool->isEmpty()) {
            return [$currentYear - 1, $currentYear, $currentYear + 1];
        }

        $years = $datePool
            ->map(fn ($value): int => (int) date('Y', strtotime((string) $value)))
            ->push($currentYear)
            ->values();

        $minYear = (int) $years->min();
        $maxYear = (int) $years->max();

        return collect(range($minYear, $maxYear))->values()->all();
    }
}
