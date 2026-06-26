<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SalesController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FinanceReportController;
use App\Http\Controllers\InventoryMovementHistoryController;
use App\Http\Controllers\MasterDataImportController;
use App\Http\Controllers\MaterialUsageController;
use App\Http\Controllers\MaterialReceiptController;
use App\Http\Controllers\ProductOpeningStockImportController;
use App\Http\Controllers\ReportAnalyticsController;

Route::middleware(['auth', 'verified'])->group(function () {
    // =========================================================================
    // Dashboard & Profile
    // =========================================================================
    Route::get('/', function () {
        return redirect()->route('dashboard');
    });
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->middleware('permission:dashboard,view')
        ->name('dashboard');
    Route::view('profile', 'profile.index')->name('profile.index');

    // =========================================================================
    // Master Data
    // =========================================================================
    Route::prefix('master')->group(function () {
        Route::view('customers', 'customers.index')
            ->middleware(['module:sales', 'permission:master_data,view'])
            ->name('customers.index');
        Route::view('suppliers', 'suppliers.index')
            ->middleware(['module:purchases', 'permission:master_data,view'])
            ->name('suppliers.index');
        Route::view('categories', 'categories.index')
            ->middleware(['module:materials', 'permission:master_data,view'])
            ->name('categories.index');
        Route::view('units', 'units.index')
            ->middleware(['module:materials', 'permission:master_data,view'])
            ->name('units.index');
        Route::get('imports/{resource}', [MasterDataImportController::class, 'show'])
            ->middleware('permission:master_data,import')
            ->name('master-imports.show');
        Route::post('imports/{resource}', [MasterDataImportController::class, 'store'])
            ->middleware('permission:master_data,import')
            ->name('master-imports.store');
        Route::get('imports/{resource}/template', [MasterDataImportController::class, 'downloadTemplate'])
            ->middleware('permission:master_data,import')
            ->name('master-imports.template');
        Route::get('products/import-opening-stock', [ProductOpeningStockImportController::class, 'index'])
            ->middleware(['module:materials', 'permission:opening_stock,import'])
            ->name('products.import-opening-stock');
        Route::post('products/import-opening-stock', [ProductOpeningStockImportController::class, 'store'])
            ->middleware(['module:materials', 'permission:opening_stock,import'])
            ->name('products.import-opening-stock.store');
        Route::get('products/import-opening-stock/template', [ProductOpeningStockImportController::class, 'downloadTemplate'])
            ->middleware(['module:materials', 'permission:opening_stock,import'])
            ->name('products.import-opening-stock.template');

        Route::view('products', 'products.index')
            ->middleware(['module:materials', 'permission:materials,view'])
            ->name('products.index');
        Route::view('batches', 'batches.index')
            ->middleware(['module:materials', 'permission:batches,view'])
            ->name('batches.index');
        Route::view('storage-locations', 'storage-locations.index')
            ->middleware(['module:materials', 'permission:master_data,view'])
            ->name('storage-locations.index');
    });

    // =========================================================================
    // Transactions
    // =========================================================================

    // Purchases
    Route::get('purchases', [PurchaseController::class, 'index'])
        ->middleware(['module:purchases', 'permission:legacy_purchase,view'])
        ->name('purchases.index');
    Route::get('purchases/create', [PurchaseController::class, 'create'])
        ->middleware(['module:purchases', 'permission:legacy_purchase,create'])
        ->name('purchases.create');
    Route::post('purchases', [PurchaseController::class, 'store'])
        ->middleware('permission:legacy_purchase,create')
        ->name('purchases.store');
    Route::get('purchases/{purchase}', [PurchaseController::class, 'show'])
        ->middleware(['module:purchases', 'permission:legacy_purchase,view'])
        ->name('purchases.show');
    Route::get('purchases/{purchase}/print', [PurchaseController::class, 'print'])
        ->middleware(['module:purchases', 'permission:legacy_purchase,view'])
        ->name('purchases.print');
    Route::get('purchases/{purchase}/edit', [PurchaseController::class, 'edit'])
        ->middleware(['module:purchases', 'permission:legacy_purchase,update'])
        ->name('purchases.edit');
    Route::match(['put', 'patch'], 'purchases/{purchase}', [PurchaseController::class, 'update'])
        ->middleware('permission:legacy_purchase,update')
        ->name('purchases.update');
    Route::delete('purchases/{purchase}', [PurchaseController::class, 'destroy'])
        ->middleware('permission:legacy_purchase,delete')
        ->name('purchases.destroy');

    Route::prefix('purchases/{purchase}')->name('purchases.')->controller(PurchaseController::class)->group(function () {
        Route::patch('ordered', 'markOrdered')->middleware('permission:legacy_purchase,confirm')->name('mark-ordered');
        Route::patch('received', 'markReceived')->middleware('permission:legacy_purchase,confirm')->name('mark-received');
        Route::patch('paid', 'markPaid')->middleware('permission:finance,confirm')->name('mark-paid');
        Route::patch('cancel', 'cancel')->middleware('permission:legacy_purchase,cancel')->name('cancel');
        Route::patch('restore-draft', 'restoreToDraft')->middleware('permission:legacy_purchase,restore')->name('restore-draft');
    });

    Route::get('material-receipts', [MaterialReceiptController::class, 'index'])
        ->middleware(['module:rni', 'permission:material_receipt,view'])
        ->name('material-receipts.index');
    Route::get('material-receipts/create', [MaterialReceiptController::class, 'create'])
        ->middleware(['module:rni', 'permission:material_receipt,create'])
        ->name('material-receipts.create');
    Route::get('material-receipts/{purchase}', [MaterialReceiptController::class, 'show'])
        ->middleware(['module:rni', 'permission:material_receipt,view'])
        ->name('material-receipts.show');
    Route::get('material-receipts/{purchase}/edit', [MaterialReceiptController::class, 'edit'])
        ->middleware(['module:rni', 'permission:material_receipt,update'])
        ->name('material-receipts.edit');

    // Sales
    Route::middleware(['module:sales'])->group(function () {
        Route::get('sales', [SalesController::class, 'index'])
            ->middleware('permission:legacy_sales,view')
            ->name('sales.index');
        Route::get('sales/create', [SalesController::class, 'create'])
            ->middleware('permission:legacy_sales,create')
            ->name('sales.create');
        Route::post('sales', [SalesController::class, 'store'])
            ->middleware('permission:legacy_sales,create')
            ->name('sales.store');
        Route::get('sales/{sale}', [SalesController::class, 'show'])
            ->middleware('permission:legacy_sales,view')
            ->name('sales.show');
        Route::delete('sales/{sale}', [SalesController::class, 'destroy'])
            ->middleware('permission:legacy_sales,cancel')
            ->name('sales.destroy');
        Route::prefix('sales/{sale}')->name('sales.')->controller(SalesController::class)->group(function () {
            Route::get('print', 'print')->middleware('permission:legacy_sales,view')->name('print');
            Route::patch('complete', 'complete')->middleware('permission:legacy_sales,confirm')->name('complete');
            Route::patch('restore', 'restore')->middleware('permission:legacy_sales,restore')->name('restore');
        });
    });

    Route::middleware(['module:rni'])->group(function () {
        Route::get('material-usages', [MaterialUsageController::class, 'index'])
            ->middleware('permission:material_usage,view')
            ->name('material-usages.index');
        Route::get('material-usages/create', [MaterialUsageController::class, 'create'])
            ->middleware('permission:material_usage,create')
            ->name('material-usages.create');
        Route::post('material-usages', [MaterialUsageController::class, 'store'])
            ->middleware('permission:material_usage,create')
            ->name('material-usages.store');
        Route::get('material-usages/{sale}', [MaterialUsageController::class, 'show'])
            ->middleware('permission:material_usage,view')
            ->name('material-usages.show');
        Route::delete('material-usages/{sale}', [MaterialUsageController::class, 'destroy'])
            ->middleware('permission:material_usage,cancel')
            ->name('material-usages.destroy');
        Route::get('material-usages/{sale}/print', [MaterialUsageController::class, 'print'])
            ->middleware('permission:material_usage,view')
            ->name('material-usages.print');
        Route::patch('material-usages/{sale}/complete', [MaterialUsageController::class, 'complete'])
            ->middleware('permission:material_usage,confirm')
            ->name('material-usages.complete');
        Route::patch('material-usages/{sale}/restore', [MaterialUsageController::class, 'restore'])
            ->middleware('permission:material_usage,restore')
            ->name('material-usages.restore');
    });

    // =========================================================================
    // Finance
    // =========================================================================
    Route::middleware(['module:finance', 'permission:finance,view'])->prefix('finance')->name('finance.')->group(function () {
        Route::view('categories', 'finance-categories.index')->name('categories.index');
        Route::view('transactions', 'finance-transactions.index')->name('transactions.index');
        Route::get('transactions/print/{printId}', [FinanceReportController::class, 'print'])->name('transactions.print');
    });

    Route::middleware(['module:reports', 'permission:reports,view'])->prefix('reports')->name('reports.')->group(function () {
        Route::view('current-inventory', 'reports.inventory')->name('inventory');
        Route::get('inventory-movement-history', [InventoryMovementHistoryController::class, 'index'])->name('inventory-movement-history');
        Route::get('inventory-movement-history/export/{format}', [InventoryMovementHistoryController::class, 'export'])
            ->middleware('permission:reports,export')
            ->name('inventory-movement-history.export');
        Route::view('usage-history', 'reports.usage-history')->name('usage-history');
        Route::view('expiry', 'reports.expiry')->name('expiry');
        Route::get('stock-movement-classification', [ReportAnalyticsController::class, 'stockMovementClassification'])
            ->name('stock-movement-classification');
    });

    Route::middleware(['module:reports', 'permission:reports,view'])->prefix('reports')->name('reports.')->group(function () {
        Route::get('purchase-analysis', [ReportAnalyticsController::class, 'purchaseAnalysis'])
            ->middleware('permission:legacy_purchase,view')
            ->name('purchase-analysis');
        Route::get('purchase-analysis/export/{format}', [ReportAnalyticsController::class, 'exportPurchaseAnalysis'])
            ->middleware(['permission:reports,export', 'permission:legacy_purchase,export'])
            ->name('purchase-analysis.export');
        Route::get('sales-analysis', [ReportAnalyticsController::class, 'salesAnalysis'])
            ->middleware('permission:legacy_sales,view')
            ->name('sales-analysis');
        Route::get('sales-analysis/export/{format}', [ReportAnalyticsController::class, 'exportSalesAnalysis'])
            ->middleware(['permission:reports,export', 'permission:legacy_sales,export'])
            ->name('sales-analysis.export');
    });

    // =========================================================================
    // Settings & Users
    // =========================================================================
    Route::view('users', 'users.index')
        ->middleware(['module:users', 'permission:user_access,view'])
        ->name('users.index');
    Route::view('roles', 'roles.index')
        ->middleware(['module:users', 'permission:user_access,view'])
        ->name('roles.index');
    Route::view('settings', 'settings.index')
        ->middleware('permission:settings,view')
        ->name('settings.index');

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
        Route::post('storage-locations', [\App\Http\Controllers\Api\StorageLocationController::class, 'search'])->name('storage-locations.search');
    });
});

require __DIR__.'/auth.php';
