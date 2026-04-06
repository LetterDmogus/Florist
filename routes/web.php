<?php

declare(strict_types=1);

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\BouquetCategoryController;
use App\Http\Controllers\BouquetTypeController;
use App\Http\Controllers\BouquetUnitController;
use App\Http\Controllers\CashierController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\ItemCategoryController;
use App\Http\Controllers\ItemUnitController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\StockMovementController;
use App\Http\Controllers\SystemHealthController;
use App\Http\Controllers\UserController;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Foundation\Application;
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

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // ─────────────────────────────────────────────────────────────────────────
    // Customer & Orders
    // ─────────────────────────────────────────────────────────────────────────
    Route::middleware('can:orders.view')->group(function () {

        // Customer
        Route::post('customers/{id}/restore', [CustomerController::class, 'restore'])->name('customers.restore')->middleware('can:customers.delete');
        Route::delete('customers/{id}/force-delete', [CustomerController::class, 'forceDelete'])->name('customers.force-delete')->middleware('can:customers.delete');
        Route::resource('customers', CustomerController::class)
            ->except(['create', 'edit']);

        Route::get('orders/status', [OrderController::class, 'statusIndex'])
            ->middleware('can:orders.status.view')
            ->name('orders.status.index');

        Route::get('cashier', [CashierController::class, 'index'])
            ->middleware('can:orders.create')
            ->name('cashier.index');

        Route::get('orders/{order}/print', [OrderController::class, 'print'])
            ->middleware('can:orders.print')
            ->name('orders.print');

        Route::get('orders/lookups/customers', [CashierController::class, 'customerLookup'])
            ->middleware('throttle:lookup-search')
            ->name('orders.lookups.customers');

        Route::get('orders/lookups/deliveries', [CashierController::class, 'deliveryLookup'])
            ->middleware('throttle:lookup-search')
            ->name('orders.lookups.deliveries');

        // Cashier checkout (order create endpoint)
        Route::post('orders', [CashierController::class, 'store'])
            ->middleware('can:orders.create')
            ->middleware('throttle:sensitive-write')
            ->name('orders.store');

        // Disable legacy order resource pages (keep route names for compatibility)
        Route::get('orders', fn () => redirect()->route('cashier.index'))->name('orders.index');
        Route::get('orders/create', fn () => redirect()->route('cashier.index'))->name('orders.create');
        Route::get('orders/{order}', fn () => redirect()->route('orders.status.index'))->name('orders.show');
        Route::get('orders/{order}/edit', [OrderController::class, 'edit'])
            ->middleware('can:orders.edit')
            ->name('orders.edit');
        Route::match(['put', 'patch'], 'orders/{order}', [OrderController::class, 'update'])
            ->middleware('can:orders.edit')
            ->name('orders.update');
        Route::delete('orders/{order}', [OrderController::class, 'destroy'])
            ->middleware('can:orders.delete')
            ->name('orders.destroy');

        Route::patch('orders/{order}/status', [OrderController::class, 'updateStatus'])
            ->middleware('can:orders.status.update')
            ->middleware('throttle:order-status-update')
            ->name('orders.status.update');
        Route::patch('orders/{order}/payment-status', [OrderController::class, 'updatePaymentStatus'])
            ->middleware('can:orders.status.update')
            ->middleware('throttle:order-status-update')
            ->name('orders.payment-status.update');

        // Order Details (nested under orders)
        Route::post('orders/{order}/details', [OrderController::class, 'storeDetail'])
            ->name('orders.details.store');
        Route::delete('orders/{order}/details/{orderDetail}', [OrderController::class, 'destroyDetail'])
            ->name('orders.details.destroy');

    });

    Route::middleware('can:deliveries.view')->group(function () {
        Route::get('deliveries/lookups/orders', [DeliveryController::class, 'orderLookup'])
            ->middleware('throttle:lookup-search')
            ->name('deliveries.lookups.orders');

        Route::post('deliveries', [DeliveryController::class, 'store'])
            ->name('deliveries.store');
        Route::post('deliveries/{id}/restore', [DeliveryController::class, 'restore'])
            ->name('deliveries.restore');
        Route::delete('deliveries/{id}/force-delete', [DeliveryController::class, 'forceDelete'])
            ->name('deliveries.force-delete');

        // Delivery (nested creation from an order)
        Route::post('orders/{order}/delivery', [DeliveryController::class, 'storeFromOrder'])
            ->name('orders.delivery.store');
        Route::put('deliveries/{delivery}', [DeliveryController::class, 'update'])
            ->name('deliveries.update');
        Route::delete('deliveries/{delivery}', [DeliveryController::class, 'destroy'])
            ->name('deliveries.destroy');
    });

    // ─────────────────────────────────────────────────────────────────────────
    // Inventory & Bouquet
    // ─────────────────────────────────────────────────────────────────────────
    Route::middleware('can:inventory.view')->group(function () {

        // Inventory
        Route::post('item-categories/{id}/restore', [ItemCategoryController::class, 'restore'])->name('item-categories.restore')->middleware('can:inventory.manage');
        Route::delete('item-categories/{id}/force-delete', [ItemCategoryController::class, 'forceDelete'])->name('item-categories.force-delete')->middleware('can:inventory.delete');
        Route::resource('item-categories', ItemCategoryController::class)
            ->except(['create', 'show', 'edit']);

        Route::get('item-units/export', [ItemUnitController::class, 'export'])
            ->name('item-units.export');

        Route::post('item-units/import', [ItemUnitController::class, 'importPreview'])
            ->middleware('throttle:sensitive-write')
            ->name('item-units.import');
        Route::post('item-units/import/commit', [ItemUnitController::class, 'importCommit'])
            ->middleware('throttle:sensitive-write')
            ->name('item-units.import.commit');
        Route::post('item-units/import/discard', [ItemUnitController::class, 'importDiscard'])
            ->middleware('throttle:sensitive-write')
            ->name('item-units.import.discard');
        Route::post('item-units/{id}/restore', [ItemUnitController::class, 'restore'])->name('item-units.restore')->middleware('can:inventory.manage');
        Route::delete('item-units/{id}/force-delete', [ItemUnitController::class, 'forceDelete'])->name('item-units.force-delete')->middleware('can:inventory.delete');
        Route::resource('item-units', ItemUnitController::class)
            ->except(['create', 'edit']);
    });

    Route::middleware('can:bouquets.view')->group(function () {
        // Bouquet
        Route::post('bouquet-categories/{id}/restore', [BouquetCategoryController::class, 'restore'])->name('bouquet-categories.restore')->middleware('can:bouquets.manage');
        Route::delete('bouquet-categories/{id}/force-delete', [BouquetCategoryController::class, 'forceDelete'])->name('bouquet-categories.force-delete')->middleware('can:bouquets.delete');
        Route::resource('bouquet-categories', BouquetCategoryController::class)
            ->except(['create', 'show', 'edit']);

        Route::post('bouquet-types/{id}/restore', [BouquetTypeController::class, 'restore'])->name('bouquet-types.restore')->middleware('can:bouquets.manage');
        Route::delete('bouquet-types/{id}/force-delete', [BouquetTypeController::class, 'forceDelete'])->name('bouquet-types.force-delete')->middleware('can:bouquets.delete');
        Route::resource('bouquet-types', BouquetTypeController::class)
            ->except(['create', 'show', 'edit']);

        Route::post('bouquet-units/{id}/restore', [BouquetUnitController::class, 'restore'])->name('bouquet-units.restore')->middleware('can:bouquets.manage');
        Route::delete('bouquet-units/{id}/force-delete', [BouquetUnitController::class, 'forceDelete'])->name('bouquet-units.force-delete')->middleware('can:bouquets.delete');
        Route::resource('bouquet-units', BouquetUnitController::class)
            ->except(['edit']);
    });

    // ─────────────────────────────────────────────────────────────────────────
    // Stock Movements & Deliveries list
    // ─────────────────────────────────────────────────────────────────────────
    Route::middleware('can:stock.view')->group(function () {

        // Stock Movements
        Route::get('stock-movements', [StockMovementController::class, 'index'])
            ->name('stock-movements.index');

        Route::get('stock-movements/lookups/items', [StockMovementController::class, 'itemLookup'])
            ->middleware('throttle:lookup-search')
            ->name('stock-movements.lookups.items');

        Route::post('stock-movements', [StockMovementController::class, 'store'])
            ->name('stock-movements.store')
            ->middleware('can:stock.manage')
            ->middleware('throttle:sensitive-write');
    });

    Route::get('deliveries', [DeliveryController::class, 'index'])
        ->name('deliveries.index')
        ->middleware('can:deliveries.view');

    // Reports
    Route::middleware('can:reports.view')->group(function () {
        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/sales', [ReportController::class, 'salesIndex'])->name('reports.sales.index');
        Route::get('reports/sales/export', [ReportController::class, 'exportSales'])
            ->middleware('can:reports.export')
            ->middleware('throttle:report-export')
            ->name('reports.sales.export');
        Route::get('reports/purchases', [ReportController::class, 'purchasesIndex'])->name('reports.purchases.index');
        Route::get('reports/purchases/export', [ReportController::class, 'exportPurchases'])
            ->middleware('can:reports.export')
            ->middleware('throttle:report-export')
            ->name('reports.purchases.export');

        Route::post('reports/entries', [ReportController::class, 'storeEntry'])
            ->name('reports.entries.store')
            ->middleware('can:reports.view')
            ->middleware('throttle:sensitive-write');
        Route::put('reports/entries/{reportEntry}', [ReportController::class, 'updateEntry'])
            ->name('reports.entries.update')
            ->middleware('can:reports.view')
            ->middleware('throttle:sensitive-write');
        Route::delete('reports/entries/{reportEntry}', [ReportController::class, 'destroyEntry'])
            ->name('reports.entries.destroy')
            ->middleware('can:reports.view')
            ->middleware('throttle:sensitive-write');
    });

    // ─────────────────────────────────────────────────────────────────────────
    // System & Settings
    // ─────────────────────────────────────────────────────────────────────────
    Route::middleware('can:settings.manage')->group(function () {
        Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::patch('settings', [SettingsController::class, 'update'])->name('settings.update');
    });

    Route::get('activities', [ActivityLogController::class, 'index'])
        ->name('activities.index')
        ->middleware('can:logs.view');

    Route::middleware('can:logs.view')->group(function () {
        Route::get('backups', [BackupController::class, 'index'])->name('backups.index');
        Route::post('backups', [BackupController::class, 'create'])->name('backups.create');
        Route::get('backups/download', [BackupController::class, 'download'])->name('backups.download');
        Route::delete('backups', [BackupController::class, 'destroy'])->name('backups.destroy');
        Route::get('system/health', SystemHealthController::class)->name('system.health');
    });

    Route::middleware('can:users.manage')->group(function () {
        Route::post('users/{id}/restore', [UserController::class, 'restore'])->name('users.restore');
        Route::delete('users/{id}/force-delete', [UserController::class, 'forceDelete'])->name('users.force-delete');
        Route::resource('users', UserController::class)->except(['create', 'show', 'edit']);
    });

    Route::middleware('can:roles.manage')->group(function () {
        Route::post('roles/{id}/restore', [RoleController::class, 'restore'])->name('roles.restore');
        Route::delete('roles/{id}/force-delete', [RoleController::class, 'forceDelete'])->name('roles.force-delete');
        Route::resource('roles', RoleController::class);

        Route::post('roles/{role}/assign-user', [RoleController::class, 'assignUser'])->name('roles.assign-user');
        Route::post('roles/{role}/revoke-user', [RoleController::class, 'revokeUser'])->name('roles.revoke-user');
    });
});
