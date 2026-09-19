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
        $type = (string) $request->string('type', 'all')->toString();
        if (! in_array($type, ['all', 'bouquet', 'supply'], true)) {
            $type = 'all';
        }

        [$year, $month, $start, $end] = $this->resolvePeriod($request);
        $data = $this->collectMonthlyData($start, $end, $type);

        return Inertia::render('Reports/Sales', [
            'activeTab' => 'sales',
            'filters' => [
                'month' => $month,
                'year' => $year,
                'type' => $type,
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
            'activeTab' => 'purchases',
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
            'canManageReportEntries' => $request->user()?->can('reports.manage') ?? false,
        ]);
    }

    public function exportSales(Request $request): BinaryFileResponse|RedirectResponse
    {
        $type = (string) $request->string('type', 'all')->toString();
        if (! in_array($type, ['all', 'bouquet', 'supply'], true)) {
            $type = 'all';
        }

        [$year, $month, $start, $end] = $this->resolvePeriod($request);
        $data = $this->collectMonthlyData($start, $end, $type);

        $typeSuffix = match ($type) {
            'bouquet' => '-bouquet',
            'supply' => '-supply',
            default => '',
        };
        $filename = sprintf('laporan-penjualan%s-%04d-%02d.xlsx', $typeSuffix, $year, $month);
        $export = new SalesReportExport(
            salesSummary: $data['salesSummary'],
            salesRows: $data['salesRows']->values()->all(),
            profitSummary: $data['profitSummary'],
            month: $month,
            year: $year,
            type: $type,
        );

        if ($request->boolean('queued')) {
            $path = "exports/{$filename}";
            Excel::queue($export, $path, 'local');

            activity('reports')
                ->causedBy($request->user())
                ->event('sales_export_queued')
                ->withProperties([
                    'year' => $year,
                    'month' => $month,
                    'type' => $type,
                    'rows' => count($data['salesRows']),
                    'filename' => $filename,
                    'path' => $path,
                ])
                ->log('report.sales_export_queued');

            return redirect()->back()
                ->with('success', "Export penjualan dimasukkan ke antrian. File: {$path}");
        }

        activity('reports')
            ->causedBy($request->user())
            ->event('sales_exported')
            ->withProperties([
                'year' => $year,
                'month' => $month,
                'type' => $type,
                'rows' => count($data['salesRows']),
                'filename' => $filename,
            ])
            ->log('report.sales_exported');

        return Excel::download($export, $filename);
    }

    public function exportPurchases(Request $request): BinaryFileResponse|RedirectResponse
    {
        [$year, $month, $start, $end] = $this->resolvePeriod($request);
        $data = $this->collectMonthlyData($start, $end);

        $filename = sprintf('laporan-pembelian-%04d-%02d.xlsx', $year, $month);
        $export = new PurchaseReportExport(
            purchaseSummary: $data['purchaseSummary'],
            purchaseRows: $data['purchaseRows']->values()->all(),
            supplyPurchaseRows: $data['supplyPurchaseRows']->values()->all(),
            storeExpenseRows: $data['storeExpenseRows']->values()->all(),
            rawMaterialRows: $data['rawMaterialRows']->values()->all(),
            shippingRows: $data['shippingRows']->values()->all(),
            refundRows: $data['refundRows']->values()->all(),
            month: $month,
            year: $year,
        );

        if ($request->boolean('queued')) {
            $path = "exports/{$filename}";
            Excel::queue($export, $path, 'local');

            activity('reports')
                ->causedBy($request->user())
                ->event('purchases_export_queued')
                ->withProperties([
                    'year' => $year,
                    'month' => $month,
                    'rows' => count($data['purchaseRows']),
                    'filename' => $filename,
                    'path' => $path,
                ])
                ->log('report.purchases_export_queued');

            return redirect()->back()
                ->with('success', "Export pembelian dimasukkan ke antrian. File: {$path}");
        }

        activity('reports')
            ->causedBy($request->user())
            ->event('purchases_exported')
            ->withProperties([
                'year' => $year,
                'month' => $month,
                'rows' => count($data['purchaseRows']),
                'filename' => $filename,
            ])
            ->log('report.purchases_exported');

        return Excel::download($export, $filename);
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
        $year = max(2000, min(2100, (int) $request->integer('year', now()->year)));
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

    private function collectMonthlyData(CarbonImmutable $start, CarbonImmutable $end, string $type = 'all'): array
    {
        $salesRows = $this->buildSalesRows($start, $end, $type);
        $totalReceivables = round($salesRows->sum('unpaid_amount'), 2);
        $totalDp = round($salesRows->sum('dp'), 2);
        $salesSummary = [
            'dp' => $totalDp,
            'money' => round($salesRows->sum('money'), 2),
            'fee' => round($salesRows->sum('fee'), 2),
            'supply_sales' => round($salesRows->sum('order_supply_income'), 2),
            'discount' => round($salesRows->sum('discount'), 2),
            'gosend' => round($salesRows->sum('gosend'), 2),
            'total' => round($salesRows->sum('total'), 2),
            'unpaid_total' => $totalReceivables,
        ];

        $reportEntries = ReportEntry::query()
            ->whereBetween('occurred_on', [$start->toDateString(), $end->toDateString()])
            ->orderBy('occurred_on')
            ->orderBy('id')
            ->get();

        $entriesByCategory = $reportEntries->groupBy('category');

        $manualSupplyIncomeTotal = $this->sumIdr($entriesByCategory->get('supply_income', collect()));
        $shippingRows = $this->buildGenericEntryRows($entriesByCategory->get('shipping_expense', collect()));
        $storeExpenseRows = $this->buildGenericEntryRows($entriesByCategory->get('store_expense', collect()));
        $rawMaterialRows = $this->buildGenericEntryRows($entriesByCategory->get('raw_material_expense', collect()));
        $profitAdjustments = $this->buildGenericEntryRows($entriesByCategory->get('profit_adjustment', collect()));
        
        // Logical split for "purchase_supply" category
        $allPurchases = $entriesByCategory->get('purchase_supply', collect());
        $physicalEntries = $allPurchases->filter(fn ($e) => str_contains($e->notes ?? '', 'Generated from Stock Movement'));
        $manualEntries = $allPurchases->filter(fn ($e) => ! str_contains($e->notes ?? '', 'Generated from Stock Movement'));

        $purchaseRows = $this->buildSupplyPurchaseRows($physicalEntries);
        $supplyPurchaseRows = $this->buildSupplyPurchaseRows($manualEntries);
        
        $refundRows = $this->buildRefundRows($entriesByCategory->get('refund', collect()));

        $storeExpenseTotal = $this->sumGenericRows($storeExpenseRows);
        $rawMaterialExpenseTotal = $this->sumGenericRows($rawMaterialRows);
        $shippingTotal = $this->sumGenericRows($shippingRows);
        
        $purchaseTotal = round($purchaseRows->sum('total'), 2);
        $supplyPurchaseTotal = round($supplyPurchaseRows->sum('total'), 2);
        $refundIdrTotal = round($refundRows->sum('idr'), 2);
        $refundRmbTotal = round($refundRows->sum('rmb'), 2);

        // Bouquet income adalah total fee buket dari order
        $orderBouquetFee = round($salesRows->sum('bouquet_fee'), 2);
        // Supply income adalah total penjualan supply dari order + entri manual supply_income
        $orderSupplySales = round($salesRows->sum('order_supply_income'), 2);
        $totalSupplyIncome = round($manualSupplyIncomeTotal + $orderSupplySales, 2);

        $profitSummary = [
            'florist_income' => $orderBouquetFee,
            'supply_income' => $totalSupplyIncome,
            'order_supply_income' => $orderSupplySales,
            'manual_supply_income' => round($manualSupplyIncomeTotal, 2),
            'purchase_total' => round($purchaseTotal + $supplyPurchaseTotal, 2), // Total all supply purchases
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
            'shipping_total' => $shippingTotal,
            'refund_idr_total' => $refundIdrTotal,
            'total_expense' => round($storeExpenseTotal + $rawMaterialExpenseTotal + $shippingTotal, 2),
            'grand_total' => round(($storeExpenseTotal + $rawMaterialExpenseTotal + $shippingTotal) + ($purchaseTotal + $supplyPurchaseTotal) - $refundIdrTotal, 2),
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

    private function buildSalesRows(CarbonImmutable $start, CarbonImmutable $end, string $type = 'all'): Collection
    {
        $orders = Order::query()
            ->with([
                'customer:id,name,phone',
                'delivery',
                'orderDetails.bouquetUnit:id,type_id,serial_number,name',
                'orderDetails.bouquetUnit.type:id,name',
                'orderDetails.inventoryItem:id,serial_number,name',
            ])
            ->whereNull('deleted_at')
            ->whereBetween('shipping_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('shipping_date')
            ->orderBy('shipping_time')
            ->orderBy('id')
            ->get();

        // Filter orders berdasarkan item_type yang terkandung jika type !== 'all'
        if ($type === 'bouquet') {
            $orders = $orders->filter(function (Order $order): bool {
                if ($order->order_type === 'custom' || $order->order_type === 'catalog') {
                    return true;
                }
                return $order->orderDetails->contains(fn ($detail) => $detail->item_type === 'bouquet');
            });
        } elseif ($type === 'supply') {
            $orders = $orders->filter(function (Order $order): bool {
                if ($order->order_type === 'inventory') {
                    return true;
                }
                return $order->orderDetails->contains(fn ($detail) => $detail->item_type === 'inventory_item');
            });
        }

        return $orders->values()->map(function (Order $order, int $index): array {
            $money = 0.0;
            $itemsSummary = [];
            $orderDetails = [];
            $itemCodes = [];
            $bouquetSubtotal = 0.0;
            $supplySubtotal = 0.0;
            $hasBouquet = false;
            $hasSupply = false;

            foreach ($order->orderDetails as $detail) {
                $model = $this->resolveModelLabel($detail);
                $isMoneyBouquet = $this->isMoneyBouquet($detail, $model);
                $detailMoney = $isMoneyBouquet ? (float) ($detail->money_bouquet ?? 0) : 0.0;
                $money += $detailMoney;
                $subtotal = (float) ($detail->subtotal ?? 0);

                $itemCode = $detail->item_type === 'bouquet'
                    ? ($detail->bouquetUnit?->serial_number)
                    : ($detail->inventoryItem?->serial_number);

                if (!empty($itemCode)) {
                    $itemCodes[] = $itemCode;
                }

                if ($detail->item_type === 'bouquet') {
                    $bouquetSubtotal += $subtotal;
                    $hasBouquet = true;
                } else {
                    $supplySubtotal += $subtotal;
                    $hasSupply = true;
                }

                $itemsSummary[] = $detail->quantity > 1
                    ? "{$model} (x{$detail->quantity})"
                    : $model;

                $orderDetails[] = [
                    'id' => $detail->id,
                    'item_code' => $itemCode ?? '-',
                    'item_name' => $model,
                    'item_type' => $detail->item_type,
                    'quantity' => (int) $detail->quantity,
                    'unit_price' => (float) ($detail->unit_price ?? 0),
                    'subtotal' => $subtotal,
                    'money_bouquet' => (float) ($detail->money_bouquet ?? 0),
                    'sender_name' => $detail->sender_name,
                    'greeting_card' => $detail->greeting_card,
                ];
            }

            $modelString = empty($itemsSummary)
                ? 'Order #' . $order->id
                : implode(', ', $itemsSummary);

            $itemCodesString = empty($itemCodes)
                ? '-'
                : implode(', ', array_unique($itemCodes));

            // Menentukan tipe order (Bouquet, Supply, atau Bouquet & Supply)
            if ($hasBouquet && $hasSupply) {
                $typeLabel = 'Bouquet & Supply';
            } elseif ($hasBouquet) {
                $typeLabel = 'Bouquet';
            } elseif ($hasSupply) {
                $typeLabel = 'Supply';
            } else {
                $typeLabel = match ($order->order_type) {
                    'inventory' => 'Supply',
                    'custom', 'catalog' => 'Bouquet',
                    default => 'Bouquet',
                };
            }

            $gosend = (float) ($order->shipping_fee ?? 0);
            $discount = (float) ($order->discount ?? 0);
            $dpAmount = (float) ($order->down_payment ?? 0);
            $orderTotal = (float) ($order->total ?? 0);

            // Perhitungan proporsi diskon jika order memiliki item buket & supply
            $itemsGrossSubtotal = $bouquetSubtotal + $supplySubtotal;
            if ($itemsGrossSubtotal > 0 && $discount > 0) {
                $bouquetDiscount = ($bouquetSubtotal / $itemsGrossSubtotal) * $discount;
                $supplyDiscount = ($supplySubtotal / $itemsGrossSubtotal) * $discount;
            } else {
                $bouquetDiscount = 0.0;
                $supplyDiscount = 0.0;
            }

            $orderBouquetFee = max(0, $bouquetSubtotal - $bouquetDiscount - $money);
            $orderSupplyIncome = max(0, $supplySubtotal - $supplyDiscount);

            // Fee dihitung sebagai subtotal order dikurangi money bouquet
            // Dimana orderTotal = (sum(subtotals) - discount) + gosend
            // Jadi items_subtotal = orderTotal - gosend + discount
            // Fee = items_subtotal - money
            $itemsSubtotal = max(0, $orderTotal - $gosend + $discount);
            $fee = max(0, $itemsSubtotal - $money);

            $unpaidAmount = 0.0;
            if ($order->payment_status !== 'paid' && $order->order_status !== 'canceled') {
                $unpaidAmount = max(0, $orderTotal - $dpAmount);
            }

            return [
                'no' => $index + 1,
                'order_id' => $order->id,
                'order_type' => $order->order_type ?? 'custom',
                'type_label' => $typeLabel,
                'item_codes' => $itemCodesString,
                'date' => $order->shipping_date?->format('Y-m-d'),
                'time' => $order->shipping_time ? substr((string) $order->shipping_time, 0, 5) : null,
                'customer_name' => $order->customer?->name ?? '-',
                'customer_phone' => $order->customer?->phone ?? '-',
                'model' => $modelString,
                'money' => round($money, 2),
                'fee' => round($fee, 2),
                'bouquet_fee' => round($orderBouquetFee, 2),
                'order_supply_income' => round($orderSupplyIncome, 2),
                'discount' => round($discount, 2),
                'gosend' => round($gosend, 2),
                'total' => round($orderTotal, 2),
                'payment_status' => (string) ($order->payment_status ?? ''),
                'order_status' => (string) ($order->order_status ?? ''),
                'is_unpaid' => in_array($order->payment_status, ['dp', 'unpaid'], true) && $order->order_status !== 'canceled',
                'is_dp' => $order->payment_status === 'dp',
                'dp' => round($dpAmount, 2),
                'unpaid_amount' => round($unpaidAmount, 2),
                'shipping_type' => $order->shipping_type,
                'delivery_address' => $order->delivery?->recipient_address,
                'delivery_recipient' => $order->delivery?->recipient_name,
                'delivery_phone' => $order->delivery?->recipient_phone,
                'items' => $orderDetails,
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
                'rate' => (float) ($entry->exchange_rate ?? 0),
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
            // Prioritaskan nama Unit (item) daripada nama Type (kategori)
            $name = $detail->bouquetUnit?->name ?? $detail->bouquetUnit?->type?->name ?? 'BOUQUET';

            return Str::upper(trim($name));
        }

        return Str::upper((string) ($detail->inventoryItem?->name ?? 'INVENTORY'));
    }

    private function isMoneyBouquet(OrderDetail $detail, string $model): bool
    {
        if ($detail->item_type !== 'bouquet') {
            return false;
        }

        // Kalau ada nilai money_bouquet di DB (misal 50.000), anggap sebagai money bouquet
        if ((float) $detail->money_bouquet > 0) {
            return true;
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
