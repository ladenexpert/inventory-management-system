<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FinanceReportController;
use App\Http\Controllers\MaterialUsageController;
use App\Http\Controllers\MaterialReceiptController;
use App\Http\Controllers\ProductOpeningStockImportController;

Route::middleware(['auth', 'verified'])->group(function () {
    // =========================================================================
    // Dashboard & Profile
    // =========================================================================
    Route::get('/', function () {
        return redirect()->route('dashboard');
    });
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::view('profile', 'profile.index')->name('profile.index');

    // =========================================================================
    // Master Data
    // =========================================================================
    Route::prefix('master')->group(function () {
        Route::middleware('role:admin_rni')->group(function () {
            Route::view('customers', 'customers.index')->name('customers.index');
            Route::view('suppliers', 'suppliers.index')->name('suppliers.index');
            Route::view('categories', 'categories.index')->name('categories.index');
            Route::view('units', 'units.index')->name('units.index');
            Route::get('products/import-opening-stock', [ProductOpeningStockImportController::class, 'index'])->name('products.import-opening-stock');
            Route::post('products/import-opening-stock', [ProductOpeningStockImportController::class, 'store'])->name('products.import-opening-stock.store');
            Route::get('products/import-opening-stock/template', [ProductOpeningStockImportController::class, 'downloadTemplate'])->name('products.import-opening-stock.template');
        });

        Route::middleware('role:admin_rni,formulator')->group(function () {
            Route::view('products', 'products.index')->name('products.index');
            Route::view('batches', 'batches.index')->name('batches.index');
        });
    });

    // =========================================================================
    // Transactions
    // =========================================================================

    // Purchases
    Route::middleware('role:admin_rni')->group(function () {
        Route::resource('purchases', PurchaseController::class);
        Route::prefix('purchases/{purchase}')->name('purchases.')->controller(PurchaseController::class)->group(function () {
            Route::patch('ordered', 'markOrdered')->name('mark-ordered');
            Route::patch('received', 'markReceived')->name('mark-received');
            Route::patch('paid', 'markPaid')->name('mark-paid');
            Route::patch('cancel', 'cancel')->name('cancel');
            Route::patch('restore-draft', 'restoreToDraft')->name('restore-draft');
        });

        Route::get('material-receipts', [MaterialReceiptController::class, 'index'])->name('material-receipts.index');
        Route::get('material-receipts/create', [MaterialReceiptController::class, 'create'])->name('material-receipts.create');
        Route::get('material-receipts/{purchase}', [MaterialReceiptController::class, 'show'])->name('material-receipts.show');
        Route::get('material-receipts/{purchase}/edit', [MaterialReceiptController::class, 'edit'])->name('material-receipts.edit');
    });

    // Sales
    Route::middleware('role:admin_rni')->group(function () {
        Route::resource('sales', SalesController::class)->except(['edit', 'update']);
        Route::prefix('sales/{sale}')->name('sales.')->controller(SalesController::class)->group(function () {
            Route::get('print', 'print')->name('print');
            Route::patch('complete', 'complete')->name('complete');
            Route::patch('restore', 'restore')->name('restore');
        });
    });

    Route::middleware('role:admin_rni,formulator')->group(function () {
        Route::resource('material-usages', MaterialUsageController::class)
            ->parameters(['material-usages' => 'sale'])
            ->except(['edit', 'update']);
        Route::prefix('material-usages/{sale}')->name('material-usages.')->controller(MaterialUsageController::class)->group(function () {
            Route::get('print', 'print')->name('print');
            Route::patch('complete', 'complete')->name('complete');
            Route::patch('restore', 'restore')->name('restore');
        });
    });

    // =========================================================================
    // Finance
    // =========================================================================
    Route::middleware('role:admin_rni')->prefix('finance')->name('finance.')->group(function () {
        Route::view('categories', 'finance-categories.index')->name('categories.index');
        Route::view('transactions', 'finance-transactions.index')->name('transactions.index');
        Route::get('transactions/print/{printId}', [FinanceReportController::class, 'print'])->name('transactions.print');
    });

    Route::middleware('role:admin_rni,formulator')->prefix('reports')->name('reports.')->group(function () {
        Route::view('current-inventory', 'reports.inventory')->name('inventory');
        Route::view('usage-history', 'reports.usage-history')->name('usage-history');
        Route::view('expiry', 'reports.expiry')->name('expiry');
    });

    // =========================================================================
    // Settings & Users
    // =========================================================================
    Route::middleware('role:admin_rni')->group(function () {
        Route::view('users', 'users.index')->name('users.index');
        Route::view('settings', 'settings.index')->name('settings.index');
    });

    // =========================================================================
    // Internal APIs (AJAX)
    // =========================================================================
    Route::prefix('ajax')->name('ajax.')->group(function () {
        Route::post('products', [\App\Http\Controllers\Api\ProductController::class, 'search'])->name('products.search');
        Route::post('suppliers', [\App\Http\Controllers\Api\SupplierController::class, 'search'])->name('suppliers.search');
        Route::post('customers', [\App\Http\Controllers\Api\CustomerController::class, 'search'])->name('customers.search');
        Route::post('customers/store', [\App\Http\Controllers\Api\CustomerController::class, 'store'])->name('customers.store');
        Route::post('categories', [\App\Http\Controllers\Api\CategoryController::class, 'search'])->name('categories.search');
        Route::post('units', [\App\Http\Controllers\Api\UnitController::class, 'search'])->name('units.search');
        Route::post('users', [\App\Http\Controllers\Api\UserController::class, 'search'])->name('users.search');
        Route::post('finance-categories', [\App\Http\Controllers\Api\FinanceCategoryController::class, 'search'])->name('finance-categories.search');
        Route::post('batches', [\App\Http\Controllers\Api\BatchController::class, 'getBatches'])->name('batches.get');
    });
});

require __DIR__.'/auth.php';
