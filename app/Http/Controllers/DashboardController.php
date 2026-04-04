<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Delivery;
use App\Models\ItemUnit;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Solusi untuk performa: Menggunakan Cache::remember agar query berat tidak dijalankan
     * setiap kali halaman di-refresh. Data diperbarui setiap 10 menit (600 detik).
     */
    public function index(Request $request): Response|RedirectResponse
    {
        $cacheKey = 'dashboard_data_' . $request->user()->id;

        if ($request->has('refresh')) {
            Cache::forget($cacheKey);
            return redirect()->route('dashboard');
        }

        $dashboardData = Cache::remember($cacheKey, 600, function () {
            return [
                'stats' => $this->getStats(),
                'chartData' => $this->getChartData(),
                'recentOrders' => $this->getRecentOrders(),
            ];
        });

        return Inertia::render('Dashboard', $dashboardData);
    }

    private function getStats(): array
    {
        $today = Carbon::today();
        $startOfWeek = Carbon::now()->startOfWeek();

        // Menggunakan Eloquent Model (Kembali ke selera awal)
        $ordersToday = Order::whereDate('created_at', $today)->count();
        $processingOrders = Order::where('order_status', 'processing')->count();

        $newCustomersThisWeek = Customer::where('created_at', '>=', $startOfWeek)->count();

        $activeDeliveries = Delivery::whereHas('order', function ($query) {
            $query->whereNotIn('order_status', ['completed', 'pending']);
        })->count();

        $lowStockCount = ItemUnit::where('stock', '<=', 5)->count();

        return [
            'ordersToday' => [
                'value' => (string) $ordersToday,
                'description' => $processingOrders . ' pesanan sedang diproses',
                'trend' => $this->getTrend(Order::class, 'created_at', 'day'),
                'trendUp' => true,
            ],
            'newCustomers' => [
                'value' => (string) $newCustomersThisWeek,
                'description' => 'Minggu ini',
                'trend' => $this->getTrend(Customer::class, 'created_at', 'week'),
                'trendUp' => true,
            ],
            'activeDeliveries' => [
                'value' => (string) $activeDeliveries,
                'description' => 'Sedang Berjalan',
                'trend' => 'Aktif',
                'trendUp' => true,
            ],
            'lowStock' => [
                'value' => (string) $lowStockCount,
                'description' => 'Perlu restok segera',
                'trend' => $lowStockCount > 0 ? 'Penting' : 'Aman',
                'trendUp' => $lowStockCount > 0 ? false : true,
            ],
        ];
    }

    private function getChartData(): array
    {
        // Tetap menggunakan Eloquent tapi dikonversi ke Array agar Cache ringan
        $weekly = Order::select([
            DB::raw('DATE(created_at) as date'),
            DB::raw('COUNT(*) as count'),
            DB::raw('SUM(total) as total'),
        ])
            ->where('created_at', '>=', now()->subDays(7))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->toArray();

        $monthly = Order::select([
            DB::raw('DATE(created_at) as date'),
            DB::raw('COUNT(*) as count'),
            DB::raw('SUM(total) as total'),
        ])
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->toArray();

        $yearly = Order::select([
            DB::raw("DATE_FORMAT(created_at, '%Y-%m') as date"),
            DB::raw('COUNT(*) as count'),
            DB::raw('SUM(total) as total'),
        ])
            ->where('created_at', '>=', now()->subYear())
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->toArray();

        return [
            'weekly' => $weekly,
            'monthly' => $monthly,
            'yearly' => $yearly,
        ];
    }

    private function getRecentOrders(): array
    {
        return Order::with('customer:id,name')
            ->latest()
            ->limit(5)
            ->get()
            ->map(function (Order $order) {
                return [
                    'id' => $order->id,
                    'total' => (float) $order->total,
                    'status' => $order->order_status,
                    'customer' => $order->customer?->name ?? 'Guest',
                    'time' => $order->created_at->diffForHumans(),
                ];
            })
            ->toArray();
    }

    private function getTrend(string $model, string $column, string $period): string
    {
        $now = Carbon::now();
        $previous = Carbon::now();

        if ($period === 'day') {
            $currentCount = $model::whereDate($column, $now)->count();
            $prevCount = $model::whereDate($column, $previous->subDay())->count();
        } else {
            $currentCount = $model::where($column, '>=', $now->startOfWeek())->count();
            $prevCount = $model::whereBetween($column, [$previous->subWeeks(2)->startOfWeek(), $previous->subWeek()->endOfWeek()])->count();
        }

        if ($prevCount == 0) return $currentCount > 0 ? '+100%' : '0%';
        
        $diff = (($currentCount - $prevCount) / $prevCount) * 100;
        return ($diff >= 0 ? '+' : '') . round($diff) . '%';
    }
}
