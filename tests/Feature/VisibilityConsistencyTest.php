<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\PurchaseStatus;
use App\Enums\SaleStatus;
use App\Enums\SaleTransactionType;
use App\Enums\UserRole;
use App\Livewire\Products\ProductTable;
use App\Livewire\Reports\InventoryReportTable;
use App\Livewire\Reports\UsageHistoryTable;
use App\Models\Batch;
use App\Models\FinanceTransaction;
use App\Models\InventoryLog;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Sale;
use App\Models\SaleItemBatch;
use App\Models\Setting;
use App\Models\User;
use App\Services\DashboardStatsService;
use App\Services\InventoryMovementHistoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Tests\TestCase;

class VisibilityConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_menu_visibility_respects_module_and_role(): void
    {
        $admin = User::factory()->create();
        $formulator = User::factory()->create([
            'role' => UserRole::FORMULATOR,
        ]);

        Setting::set('module_finance_enabled', '1');

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Dashboard')
            ->assertSee('Operations')
            ->assertSee('Master Data')
            ->assertSee('Reports')
            ->assertSee('Administration')
            ->assertSee('Transactions');

        $this->actingAs($admin)
            ->get(route('finance.transactions.index'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('material-receipts.index'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('reports.inventory'))
            ->assertOk();

        $this->actingAs($formulator)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Operations')
            ->assertDontSee('Transactions');

        Setting::set('module_finance_enabled', '0');

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Transactions');
    }

    public function test_material_receipt_updates_dashboard_inventory_report_and_movement_history_immediately(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'sku' => 'RM-SYNC-001',
            'item_code_ierp' => 'IERP-RM-SYNC-001',
            'quantity' => 0,
            'purchase_price' => 5000,
            'selling_price' => 6500,
        ]);

        $purchase = Purchase::create([
            'supplier_id' => null,
            'purchase_date' => now(),
            'status' => PurchaseStatus::ORDERED,
            'created_by' => $user->id,
            'entry_context' => 'material_receipt',
            'total' => 25000,
        ]);

        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'batch_number' => 'MR-VIS-001',
            'expiry_date' => now()->addMonths(6)->toDateString(),
            'storage_location' => 'RM-01',
            'quantity' => 5,
            'unit_price' => 5000,
            'selling_price' => 6500,
            'subtotal' => 25000,
        ]);

        $service = app(DashboardStatsService::class);
        $this->assertSame(0, $service->getRniOverviewStats()['total_physical_stock_quantity']);

        $this->actingAs($user)
            ->patch(route('purchases.mark-received', $purchase))
            ->assertRedirect(route('material-receipts.show', $purchase));

        $purchase->refresh();

        $this->assertSame(PurchaseStatus::RECEIVED, $purchase->status);
        $this->assertSame(0, FinanceTransaction::count());
        $this->assertSame(5, $service->getRniOverviewStats()['total_physical_stock_quantity']);

        $historyRows = app(InventoryMovementHistoryService::class)->exportRows();
        $this->assertTrue($historyRows->contains(fn (array $row) => $row['item_code_ierp'] === 'IERP-RM-SYNC-001' && $row['lot_number'] === 'MR-VIS-001'));

        $this->actingAs($user);
        Livewire::test(InventoryReportTable::class)
            ->assertSee('IERP-RM-SYNC-001')
            ->assertSee('MR-VIS-001');
    }

    public function test_material_usage_updates_dashboard_and_usage_analysis_immediately(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'sku' => 'RM-USAGE-001',
            'item_code_ierp' => 'IERP-RM-USAGE-001',
            'quantity' => 10,
            'purchase_price' => 12000,
            'selling_price' => 16000,
        ]);

        Batch::create([
            'product_id' => $product->id,
            'batch_number' => 'USAGE-BATCH-001',
            'expiry_date' => now()->addMonths(4)->toDateString(),
            'received_at' => now()->subDay(),
            'storage_location' => 'RM-B2',
            'unit_cost' => 12000,
            'selling_price' => 16000,
            'quantity' => 10,
            'available_quantity' => 10,
            'source' => 'purchase',
        ]);

        $service = app(DashboardStatsService::class);
        $this->assertSame(0, $service->getRniOverviewStats()['material_usage_this_month']);

        $this->actingAs($user)
            ->postJson(route('material-usages.store'), [
                'usage_date' => now()->toDateString(),
                'purpose' => 'Immediate visibility',
                'issued_by' => $user->id,
                'items' => [
                    [
                        'product_id' => $product->id,
                        'quantity' => 4,
                        'unit_price' => 12000,
                        'discount' => 0,
                    ],
                ],
            ])
            ->assertCreated();

        $usage = Sale::query()->latest('id')->firstOrFail();

        $this->assertSame(SaleTransactionType::MATERIAL_USAGE, $usage->transaction_type);
        $this->assertSame(0, FinanceTransaction::where('reference_type', Sale::class)->where('reference_id', $usage->id)->count());
        $this->assertSame(4, $service->getRniOverviewStats()['material_usage_this_month']);

        $this->actingAs($user);
        Livewire::test(UsageHistoryTable::class)
            ->assertSee($usage->invoice_number)
            ->assertSee('IERP-RM-USAGE-001')
            ->assertSee('USAGE-BATCH-001');
    }

    public function test_legacy_purchase_payment_and_legacy_sale_complete_refresh_finance_and_analysis(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'sku' => 'LEG-001',
            'item_code_ierp' => 'IERP-LEG-001',
            'quantity' => 8,
            'purchase_price' => 7000,
            'selling_price' => 12000,
        ]);

        Batch::create([
            'product_id' => $product->id,
            'batch_number' => 'LEG-SALE-BATCH-001',
            'expiry_date' => now()->addMonths(3)->toDateString(),
            'received_at' => now()->subDay(),
            'storage_location' => 'FG-01',
            'unit_cost' => 7000,
            'selling_price' => 12000,
            'quantity' => 8,
            'available_quantity' => 8,
            'source' => 'purchase',
        ]);

        $purchase = Purchase::create([
            'supplier_id' => null,
            'invoice_number' => 'PO-FIN-001',
            'purchase_date' => now(),
            'proof_image' => 'proofs/existing-proof.jpg',
            'total' => 18000,
            'status' => PurchaseStatus::RECEIVED,
            'created_by' => $user->id,
            'entry_context' => 'legacy_purchase',
        ]);

        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'batch_number' => 'PO-FIN-BATCH-001',
            'expiry_date' => now()->addMonths(8)->toDateString(),
            'storage_location' => 'RM-C1',
            'quantity' => 3,
            'unit_price' => 6000,
            'selling_price' => 9000,
            'subtotal' => 18000,
        ]);

        $dashboard = app(DashboardStatsService::class);
        $this->assertSame(0.0, $dashboard->getCashFlowStats(now()->subDays(29)->startOfDay(), now()->endOfDay(), 'last_30_days')['expense']);

        $this->actingAs($user)
            ->patch(route('purchases.mark-paid', $purchase))
            ->assertRedirect(route('purchases.show', $purchase));

        $purchaseRows = $dashboard->getPurchaseAnalysisRows(now()->subDays(29)->startOfDay(), now()->endOfDay());
        $this->assertTrue($purchaseRows->contains(fn (array $row) => $row['reference'] === 'PO-FIN-001' && $row['item_code_ierp'] === 'IERP-LEG-001'));
        $this->assertSame(18000.0, $dashboard->getCashFlowStats(now()->subDays(29)->startOfDay(), now()->endOfDay(), 'last_30_days')['expense']);

        $this->actingAs($user)
            ->postJson(route('sales.store'), [
                'sale_date' => now()->toDateString(),
                'payment_method' => PaymentMethod::TRANSFER->value,
                'status' => SaleStatus::COMPLETED->value,
                'global_discount' => 0,
                'items' => [
                    [
                        'product_id' => $product->id,
                        'quantity' => 2,
                        'unit_price' => 12000,
                        'discount' => 1000,
                    ],
                ],
            ])
            ->assertCreated();

        $sale = Sale::query()->latest('id')->firstOrFail();
        $salesRows = $dashboard->getSalesAnalysisRows(now()->subDays(29)->startOfDay(), now()->endOfDay());

        $this->assertSame(1, FinanceTransaction::where('reference_type', Purchase::class)->where('reference_id', $purchase->id)->count());
        $this->assertSame(1, FinanceTransaction::where('reference_type', Sale::class)->where('reference_id', $sale->id)->count());
        $this->assertTrue($salesRows->contains(fn (array $row) => $row['invoice_number'] === $sale->invoice_number && $row['item_code_ierp'] === 'IERP-LEG-001'));

        $this->get(route('reports.sales-analysis.export', ['format' => 'csv']))
            ->assertOk();
    }

    public function test_item_code_ierp_remains_nullable_and_separate_from_sku_in_lists_and_history(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'sku' => 'SKU-SEPARATE-001',
            'item_code_ierp' => null,
            'quantity' => 6,
        ]);

        $batch = Batch::create([
            'product_id' => $product->id,
            'batch_number' => 'SEPARATE-BATCH-001',
            'expiry_date' => now()->addMonths(2)->toDateString(),
            'received_at' => now()->subDay(),
            'storage_location' => 'RM-F1',
            'unit_cost' => 5000,
            'selling_price' => 7000,
            'quantity' => 6,
            'available_quantity' => 6,
            'source' => 'opening_balance',
        ]);

        InventoryLog::create([
            'product_id' => $product->id,
            'batch_id' => $batch->id,
            'movement_type' => 'opening_balance',
            'quantity' => 6,
            'quantity_before' => 0,
            'quantity_after' => 6,
            'notes' => 'Separated code visibility test',
        ]);

        $this->assertSame('SKU-SEPARATE-001', $product->sku_display);
        $this->assertSame('-', $product->item_code_ierp_display);

        $productFields = app(ProductTable::class)->fields()->fields;
        $inventoryFields = app(InventoryReportTable::class)->fields()->fields;

        $this->assertSame('SKU-SEPARATE-001', $productFields['sku']($product));
        $this->assertSame('-', $productFields['item_code_ierp']($product));
        $this->assertSame('SKU-SEPARATE-001', $inventoryFields['sku']($batch));
        $this->assertSame('-', $inventoryFields['item_code_ierp']($batch));

        $this->actingAs($user);

        Livewire::test(ProductTable::class)->assertSee('SKU-SEPARATE-001');
        Livewire::test(InventoryReportTable::class)->assertSee('SKU-SEPARATE-001');

        $historyRows = app(InventoryMovementHistoryService::class)->exportRows();
        $this->assertTrue($historyRows->contains(
            fn (array $row) => $row['sku'] === 'SKU-SEPARATE-001'
                && $row['item_code_ierp'] === '-'
                && $row['lot_number'] === 'SEPARATE-BATCH-001'
        ));
    }

    public function test_exports_and_usage_analysis_keep_sku_and_item_code_ierp_separate(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'sku' => 'SKU-EXPORT-001',
            'item_code_ierp' => null,
            'quantity' => 10,
            'purchase_price' => 7000,
            'selling_price' => 11000,
        ]);

        $batch = Batch::create([
            'product_id' => $product->id,
            'batch_number' => 'EXPORT-BATCH-001',
            'expiry_date' => now()->addMonths(4)->toDateString(),
            'received_at' => now()->subDay(),
            'storage_location' => 'RM-E1',
            'unit_cost' => 7000,
            'selling_price' => 11000,
            'quantity' => 10,
            'available_quantity' => 10,
            'source' => 'purchase',
        ]);

        $purchase = Purchase::create([
            'supplier_id' => null,
            'invoice_number' => 'PO-EXPORT-001',
            'purchase_date' => now(),
            'status' => PurchaseStatus::RECEIVED,
            'created_by' => $user->id,
            'entry_context' => 'legacy_purchase',
            'total' => 14000,
        ]);

        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'batch_number' => 'PO-EXPORT-BATCH-001',
            'expiry_date' => now()->addMonths(6)->toDateString(),
            'storage_location' => 'RM-E2',
            'quantity' => 2,
            'unit_price' => 7000,
            'selling_price' => 11000,
            'subtotal' => 14000,
        ]);

        $this->actingAs($user)
            ->postJson(route('material-usages.store'), [
                'usage_date' => now()->toDateString(),
                'purpose' => 'Usage export validation',
                'issued_by' => $user->id,
                'items' => [
                    [
                        'product_id' => $product->id,
                        'quantity' => 2,
                        'unit_price' => 7000,
                        'discount' => 0,
                    ],
                ],
            ])
            ->assertCreated();

        $usageBatch = SaleItemBatch::query()->with(['batch.product', 'saleItem.product'])->latest('id')->firstOrFail();
        $usageFields = app(UsageHistoryTable::class)->fields()->fields;

        $this->assertSame('SKU-EXPORT-001', $usageFields['sku']($usageBatch));
        $this->assertSame('-', $usageFields['item_code_ierp']($usageBatch));

        $this->actingAs($user)
            ->postJson(route('sales.store'), [
                'sale_date' => now()->toDateString(),
                'payment_method' => PaymentMethod::TRANSFER->value,
                'status' => SaleStatus::COMPLETED->value,
                'global_discount' => 0,
                'items' => [
                    [
                        'product_id' => $product->id,
                        'quantity' => 1,
                        'unit_price' => 11000,
                        'discount' => 0,
                    ],
                ],
            ])
            ->assertCreated();

        $dashboard = app(DashboardStatsService::class);
        $purchaseRows = $dashboard->getPurchaseAnalysisRows(now()->subDays(29)->startOfDay(), now()->endOfDay());
        $salesRows = $dashboard->getSalesAnalysisRows(now()->subDays(29)->startOfDay(), now()->endOfDay());

        $this->assertTrue($purchaseRows->contains(
            fn (array $row) => $row['reference'] === 'PO-EXPORT-001'
                && $row['sku'] === 'SKU-EXPORT-001'
                && $row['item_code_ierp'] === '-'
        ));
        $this->assertTrue($salesRows->contains(
            fn (array $row) => $row['sku'] === 'SKU-EXPORT-001'
                && $row['item_code_ierp'] === '-'
        ));

        $salesExport = $this->actingAs($user)
            ->get(route('reports.sales-analysis.export', ['format' => 'csv']))
            ->assertOk();
        $purchaseExport = $this->actingAs($user)
            ->get(route('reports.purchase-analysis.export', ['format' => 'csv']))
            ->assertOk();
        $movementExport = $this->actingAs($user)
            ->get(route('reports.inventory-movement-history.export', ['format' => 'csv']))
            ->assertOk();

        $salesCsv = $this->downloadedFileContent($salesExport);
        $purchaseCsv = $this->downloadedFileContent($purchaseExport);
        $movementCsv = $this->downloadedFileContent($movementExport);

        $this->assertStringContainsString('SKU,"Item Code IERP"', $salesCsv);
        $this->assertStringContainsString('SKU-EXPORT-001,-', $salesCsv);
        $this->assertStringContainsString('SKU,"Item Code IERP"', $purchaseCsv);
        $this->assertStringContainsString('SKU-EXPORT-001,-', $purchaseCsv);
        $this->assertStringContainsString('"Material / Product Name",SKU,"Item Code IERP"', $movementCsv);
        $this->assertStringContainsString('SKU-EXPORT-001,-', $movementCsv);
    }

    private function downloadedFileContent(TestResponse $response): string
    {
        $file = $response->baseResponse->getFile();

        $this->assertNotNull($file);

        return (string) file_get_contents($file->getPathname());
    }
}
