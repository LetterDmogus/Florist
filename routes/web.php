<?php

declare(strict_types=1);

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\BouquetCategoryController;
use App\Http\Controllers\BouquetTypeController;
use App\Http\Controllers\BouquetUnitController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\ItemCategoryController;
use App\Http\Controllers\ItemUnitController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\StockMovementController;
use App\Http\Controllers\UserController;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Role;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {

    Route::get('/dashboard', function () {
        // MySQL compatible date functions (assuming MySQL based on logs)
        $weeklyData = Order::select([
            DB::raw('DATE(created_at) as date'),
            DB::raw('COUNT(*) as count'),
            DB::raw('SUM(total) as total'),
        ])
            ->where('created_at', '>=', now()->subDays(7))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $monthlyData = Order::select([
            DB::raw('DATE(created_at) as date'),
            DB::raw('COUNT(*) as count'),
            DB::raw('SUM(total) as total'),
        ])
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $yearlyData = Order::select([
            DB::raw("DATE_FORMAT(created_at, '%Y-%m') as date"),
            DB::raw('COUNT(*) as count'),
            DB::raw('SUM(total) as total'),
        ])
            ->where('created_at', '>=', now()->subYear())
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return Inertia::render('Dashboard', [
            'chartData' => [
                'weekly' => $weeklyData,
                'monthly' => $monthlyData,
                'yearly' => $yearlyData,
            ],
        ]);
    })->name('dashboard');

    // ─────────────────────────────────────────────────────────────────────────
    // Kasir & Admin & Super Admin — Customer & Orders
    // ─────────────────────────────────────────────────────────────────────────
    Route::middleware('role:super-admin|admin|kasir')->group(function () {

        // Customer
        Route::post('customers/{id}/restore', [CustomerController::class, 'restore'])->name('customers.restore');
        Route::delete('customers/{id}/force-delete', [CustomerController::class, 'forceDelete'])->name('customers.force-delete');
        Route::resource('customers', CustomerController::class)
            ->except(['create', 'edit']);

        Route::get('orders/status', [OrderController::class, 'statusIndex'])
            ->middleware('role:super-admin|admin')
            ->name('orders.status.index');

        Route::get('orders/{order}/print', [OrderController::class, 'print'])
            ->name('orders.print');

        // Orders
        Route::resource('orders', OrderController::class)
            ->except(['edit']);

        Route::patch('orders/{order}/status', [OrderController::class, 'updateStatus'])
            ->middleware('role:super-admin|admin')
            ->name('orders.status.update');

        // Order Details (nested under orders)
        Route::post('orders/{order}/details', [OrderController::class, 'storeDetail'])
            ->name('orders.details.store');
        Route::delete('orders/{order}/details/{orderDetail}', [OrderController::class, 'destroyDetail'])
            ->name('orders.details.destroy');

    });

    Route::middleware('role:super-admin|admin|kasir|manager')->group(function () {
        // Delivery (nested creation from an order)
        Route::post('orders/{order}/delivery', [DeliveryController::class, 'storeFromOrder'])
            ->name('orders.delivery.store');
        Route::put('deliveries/{delivery}', [DeliveryController::class, 'update'])
            ->name('deliveries.update');
        Route::delete('deliveries/{delivery}', [DeliveryController::class, 'destroy'])
            ->name('deliveries.destroy');
    });

    // ─────────────────────────────────────────────────────────────────────────
    // Admin & Super Admin — Master Data (Inventory & Bouquet)
    // ─────────────────────────────────────────────────────────────────────────
    Route::middleware('role:super-admin|admin')->group(function () {

        // Inventory
        Route::post('item-categories/{id}/restore', [ItemCategoryController::class, 'restore'])->name('item-categories.restore');
        Route::delete('item-categories/{id}/force-delete', [ItemCategoryController::class, 'forceDelete'])->name('item-categories.force-delete');
        Route::resource('item-categories', ItemCategoryController::class)
            ->except(['create', 'show', 'edit']);
        Route::post('item-units/{id}/restore', [ItemUnitController::class, 'restore'])->name('item-units.restore');
        Route::delete('item-units/{id}/force-delete', [ItemUnitController::class, 'forceDelete'])->name('item-units.force-delete');
        Route::resource('item-units', ItemUnitController::class)
            ->except(['create', 'edit']);

        // Bouquet
        Route::post('bouquet-categories/{id}/restore', [BouquetCategoryController::class, 'restore'])->name('bouquet-categories.restore');
        Route::delete('bouquet-categories/{id}/force-delete', [BouquetCategoryController::class, 'forceDelete'])->name('bouquet-categories.force-delete');
        Route::resource('bouquet-categories', BouquetCategoryController::class)
            ->except(['create', 'show', 'edit']);

        Route::post('bouquet-types/{id}/restore', [BouquetTypeController::class, 'restore'])->name('bouquet-types.restore');
        Route::delete('bouquet-types/{id}/force-delete', [BouquetTypeController::class, 'forceDelete'])->name('bouquet-types.force-delete');
        Route::resource('bouquet-types', BouquetTypeController::class)
            ->except(['create', 'show', 'edit']);

        Route::post('bouquet-units/{id}/restore', [BouquetUnitController::class, 'restore'])->name('bouquet-units.restore');
        Route::delete('bouquet-units/{id}/force-delete', [BouquetUnitController::class, 'forceDelete'])->name('bouquet-units.force-delete');
        Route::resource('bouquet-units', BouquetUnitController::class)
            ->except(['edit']);
    });

    // ─────────────────────────────────────────────────────────────────────────
    // Admin, Manager & Super Admin — Stock Movements & Deliveries list
    // ─────────────────────────────────────────────────────────────────────────
    Route::middleware('role:super-admin|admin|manager')->group(function () {

        // Stock Movements (read-only untuk manager)
        Route::get('stock-movements', [StockMovementController::class, 'index'])
            ->name('stock-movements.index');

        // Deliveries list
        Route::get('deliveries', [DeliveryController::class, 'index'])
            ->name('deliveries.index');
        Route::post('deliveries', [DeliveryController::class, 'store'])
            ->name('deliveries.store');
        Route::post('deliveries/{id}/restore', [DeliveryController::class, 'restore'])
            ->name('deliveries.restore');
        Route::delete('deliveries/{id}/force-delete', [DeliveryController::class, 'forceDelete'])
            ->name('deliveries.force-delete');

        // Reports
        Route::get('reports', [ReportController::class, 'index'])
            ->name('reports.index');
        Route::get('reports/sales', [ReportController::class, 'salesIndex'])
            ->name('reports.sales.index');
        Route::get('reports/sales/export', [ReportController::class, 'exportSales'])
            ->name('reports.sales.export');
        Route::get('reports/purchases', [ReportController::class, 'purchasesIndex'])
            ->name('reports.purchases.index');
        Route::get('reports/purchases/export', [ReportController::class, 'exportPurchases'])
            ->name('reports.purchases.export');
    });

    // Stock Movement create — hanya admin & super-admin
    Route::middleware('role:super-admin|admin')->group(function () {
        Route::post('stock-movements', [StockMovementController::class, 'store'])
            ->name('stock-movements.store');

        Route::post('reports/entries', [ReportController::class, 'storeEntry'])
            ->name('reports.entries.store');
        Route::put('reports/entries/{reportEntry}', [ReportController::class, 'updateEntry'])
            ->name('reports.entries.update');
        Route::delete('reports/entries/{reportEntry}', [ReportController::class, 'destroyEntry'])
            ->name('reports.entries.destroy');
    });

    // ─────────────────────────────────────────────────────────────────────────
    // Super Admin only — Role & Permission management & Settings
    // ─────────────────────────────────────────────────────────────────────────
    Route::middleware('role:super-admin')->group(function () {
        Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::patch('settings', [SettingsController::class, 'update'])->name('settings.update');

        Route::get('activities', [ActivityLogController::class, 'index'])->name('activities.index');

        Route::post('users/{id}/restore', [UserController::class, 'restore'])->name('users.restore');
        Route::delete('users/{id}/force-delete', [UserController::class, 'forceDelete'])->name('users.force-delete');
        Route::resource('users', UserController::class)
            ->except(['create', 'show', 'edit']);

        Route::post('roles/{id}/restore', [RoleController::class, 'restore'])->name('roles.restore');
        Route::delete('roles/{id}/force-delete', [RoleController::class, 'forceDelete'])->name('roles.force-delete');
        Route::resource('roles', RoleController::class)
            ->except(['create', 'show', 'edit']);

        Route::post('roles/{role}/assign-user', [RoleController::class, 'assignUser'])
            ->name('roles.assign-user');
        Route::post('roles/{role}/revoke-user', [RoleController::class, 'revokeUser'])
            ->name('roles.revoke-user');
    });
});
