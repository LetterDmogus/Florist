<?php

declare(strict_types=1);

use App\Http\Controllers\BouquetCategoryController;
use App\Http\Controllers\BouquetTypeController;
use App\Http\Controllers\BouquetUnitController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DeliveryController;
use App\Http\Controllers\ItemCategoryController;
use App\Http\Controllers\ItemUnitController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\StockMovementController;
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

    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard');
    })->name('dashboard');

    // ─────────────────────────────────────────────────────────────────────────
    // Kasir & Admin & Super Admin — Customer & Orders
    // ─────────────────────────────────────────────────────────────────────────
    Route::middleware('role:super-admin|admin|kasir')->group(function () {

        // Customer
        Route::resource('customers', CustomerController::class)
            ->except(['create', 'edit']);

        // Orders
        Route::resource('orders', OrderController::class)
            ->except(['edit']);

        // Order Details (nested under orders)
        Route::post('orders/{order}/details', [OrderController::class, 'storeDetail'])
            ->name('orders.details.store');
        Route::delete('orders/{order}/details/{orderDetail}', [OrderController::class, 'destroyDetail'])
            ->name('orders.details.destroy');

        // Delivery (nested creation from an order)
        Route::post('orders/{order}/delivery', [DeliveryController::class, 'store'])
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
        Route::resource('item-categories', ItemCategoryController::class)
            ->except(['create', 'show', 'edit']);
        Route::resource('item-units', ItemUnitController::class)
            ->except(['edit']);

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
    });

    // Stock Movement create — hanya admin & super-admin
    Route::middleware('role:super-admin|admin')->group(function () {
        Route::post('stock-movements', [StockMovementController::class, 'store'])
            ->name('stock-movements.store');
    });

    // ─────────────────────────────────────────────────────────────────────────
    // Super Admin only — Role & Permission management
    // ─────────────────────────────────────────────────────────────────────────
    Route::middleware('role:super-admin')->group(function () {

        Route::resource('roles', RoleController::class)
            ->except(['create', 'show', 'edit']);

        Route::post('roles/{role}/assign-user', [RoleController::class, 'assignUser'])
            ->name('roles.assign-user');
        Route::post('roles/{role}/revoke-user', [RoleController::class, 'revokeUser'])
            ->name('roles.revoke-user');
    });
});
