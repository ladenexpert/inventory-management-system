<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\PurchaseStatus;
use App\Enums\SaleStatus;
use App\Enums\SaleTransactionType;
use App\Livewire\MaterialUsages\MaterialUsageTable;
use App\Livewire\Purchases\PurchaseTable;
use App\Livewire\Reports\UsageHistoryTable;
use App\Livewire\Sales\SalesTable;
use App\Models\Batch;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleItemBatch;
use App\Models\Team;
use App\Models\User;
use App\Services\DashboardStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Tests\TestCase;

class TransactionContextFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_material_usage_operations_list_is_header_level_while_usage_report_stays_line_level(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['name' => 'Granulation']);
        [$firstProduct, $firstBatch] = $this->seedUsageProduct('RM-CTX-001', 'CTX-BATCH-001', 10);
        [$secondProduct, $secondBatch] = $this->seedUsageProduct('RM-CTX-002', 'CTX-BATCH-002', 12);

        $this->actingAs($user)
            ->postJson(route('material-usages.store'), [
                'usage_date' => now()->toDateString(),
                'invoice_number' => 'REQ-CTX-001',
                'purpose' => 'Context split validation',
                'team_id' => $team->id,
                'requested_by' => 'RNI Team',
                'issued_by' => $user->id,
                'items' => [
                    [
                        'product_id' => $firstProduct->id,
                        'quantity' => 3,
                        'unit_price' => 0,
                        'discount' => 0,
                        'batch_allocations' => [
                            ['batch_id' => $firstBatch->id, 'quantity' => 3],
                        ],
                    ],
                    [
                        'product_id' => $secondProduct->id,
                        'quantity' => 2,
                        'unit_price' => 0,
                        'discount' => 0,
                        'batch_allocations' => [
                            ['batch_id' => $secondBatch->id, 'quantity' => 2],
                        ],
                    ],
                ],
            ])
            ->assertCreated();

        $usage = Sale::query()->latest('id')->firstOrFail();

        $this->actingAs($user);
        $this->bindRoute('material-usages.index');

        $operationRows = app(MaterialUsageTable::class)->datasource()->get();
        $reportRows = app(UsageHistoryTable::class)->datasource()->get();

        $this->assertCount(1, $operationRows);
        $this->assertSame($usage->id, $operationRows->first()->id);
        $this->assertSame(2, (int) $operationRows->first()->items_count);
        $this->assertSame(5, (int) $operationRows->first()->total_quantity);
        $this->assertCount(2, $reportRows);

        $this->get(route('material-usages.index'))
            ->assertOk()
            ->assertSee('Material Usage');
    }

    public function test_purchase_exports_stay_in_their_contexts_with_clean_values_and_filenames(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'sku' => 'SKU-PUR-CTX',
            'item_code_ierp' => 'IERP-PUR-CTX',
        ]);

        $materialReceipt = $this->seedReceivedPurchase(
            $user,
            $product,
            'material_receipt',
            'MR.260626.0001',
            'DN-CTX-001',
            'MR-BATCH-CTX',
        );

        $legacyPurchase = $this->seedReceivedPurchase(
            $user,
            $product,
            'legacy_purchase',
            'PO.260626.0001',
            'PO-CTX-001',
            'PO-BATCH-CTX',
        );

        $this->actingAs($user);

        $receiptExport = $this->exportComponentCsv(PurchaseTable::class, ['context' => 'material_receipt']);
        $receiptCsv = $this->downloadedFileContent($receiptExport);

        $receiptExport->assertDownload('material_receipt_lines_' . now()->format('Y_m_d') . '.csv');
        $this->assertStringContainsString($materialReceipt->display_transaction_number, $receiptCsv);
        $this->assertStringNotContainsString($legacyPurchase->display_transaction_number, $receiptCsv);
        $this->assertStringNotContainsString('<span', $receiptCsv);
        $this->assertStringNotContainsString('<svg', $receiptCsv);

        $purchaseExport = $this->exportComponentCsv(PurchaseTable::class, ['context' => 'legacy_purchase']);
        $purchaseCsv = $this->downloadedFileContent($purchaseExport);

        $purchaseExport->assertDownload('legacy_purchase_lines_' . now()->format('Y_m_d') . '.csv');
        $this->assertStringContainsString($legacyPurchase->display_transaction_number, $purchaseCsv);
        $this->assertStringNotContainsString($materialReceipt->display_transaction_number, $purchaseCsv);
        $this->assertStringNotContainsString('<span', $purchaseCsv);
        $this->assertStringNotContainsString('<svg', $purchaseCsv);
    }

    public function test_material_usage_and_legacy_sales_exports_stay_in_their_contexts_with_clean_values_and_filenames(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['name' => 'Compression']);
        $product = Product::factory()->create([
            'sku' => 'SKU-SALE-CTX',
            'item_code_ierp' => 'IERP-SALE-CTX',
            'purchase_price' => 7000,
            'selling_price' => 11000,
        ]);

        $materialUsage = $this->seedSale(
            $user,
            $product,
            SaleTransactionType::MATERIAL_USAGE,
            'MU.260626.0001',
            'REQ-CTX-002',
            team: $team,
            quantity: 2,
        );

        $legacySale = $this->seedSale(
            $user,
            $product,
            SaleTransactionType::SALE,
            'INV.260626.0001',
            'INV-CTX-001',
            quantity: 1,
        );

        $this->actingAs($user);

        $this->bindRoute('material-usages.index');
        $usageExport = $this->exportComponentCsv(MaterialUsageTable::class);
        $usageCsv = $this->downloadedFileContent($usageExport);

        $usageExport->assertDownload('material_usage_lines_' . now()->format('Y_m_d') . '.csv');
        $this->assertStringContainsString($materialUsage->display_transaction_number, $usageCsv);
        $this->assertStringNotContainsString($legacySale->display_transaction_number, $usageCsv);
        $this->assertStringNotContainsString('<span', $usageCsv);
        $this->assertStringNotContainsString('<svg', $usageCsv);

        $legacySalesExport = $this->exportComponentCsv(SalesTable::class);
        $legacySalesCsv = $this->downloadedFileContent($legacySalesExport);

        $legacySalesExport->assertDownload('legacy_sales_lines_' . now()->format('Y_m_d') . '.csv');
        $this->assertStringContainsString($legacySale->display_transaction_number, $legacySalesCsv);
        $this->assertStringNotContainsString($materialUsage->display_transaction_number, $legacySalesCsv);
        $this->assertStringNotContainsString('<span', $legacySalesCsv);
        $this->assertStringNotContainsString('<svg', $legacySalesCsv);
    }

    public function test_analysis_exports_make_purchase_scope_explicit_and_exclude_material_usage_from_sales_analysis(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $product = Product::factory()->create([
            'sku' => 'SKU-ANL-CTX',
            'item_code_ierp' => 'IERP-ANL-CTX',
            'purchase_price' => 8000,
            'selling_price' => 12000,
        ]);

        $materialReceipt = $this->seedReceivedPurchase(
            $user,
            $product,
            'material_receipt',
            'MR.260626.0002',
            'DN-CTX-002',
            'MR-ANL-BATCH',
        );

        $legacyPurchase = $this->seedReceivedPurchase(
            $user,
            $product,
            'legacy_purchase',
            'PO.260626.0002',
            'PO-CTX-002',
            'PO-ANL-BATCH',
        );

        $legacySale = $this->seedSale(
            $user,
            $product,
            SaleTransactionType::SALE,
            'INV.260626.0002',
            'INV-CTX-002',
            quantity: 1,
        );

        $materialUsage = $this->seedSale(
            $user,
            $product,
            SaleTransactionType::MATERIAL_USAGE,
            'MU.260626.0002',
            'REQ-CTX-003',
            team: $team,
            quantity: 2,
        );

        $purchaseExport = $this->actingAs($user)
            ->get(route('reports.purchase-analysis.export', ['format' => 'csv']))
            ->assertOk();

        $salesExport = $this->actingAs($user)
            ->get(route('reports.sales-analysis.export', ['format' => 'csv']))
            ->assertOk();

        $purchaseExport->assertDownload('inbound_purchase_analysis_' . now()->format('Y_m_d') . '.csv');
        $salesExport->assertDownload('sales_analysis_' . now()->format('Y_m_d') . '.csv');

        $purchaseCsv = $this->downloadedFileContent($purchaseExport);
        $salesCsv = $this->downloadedFileContent($salesExport);

        $this->assertStringContainsString('Context', $purchaseCsv);
        $this->assertStringContainsString($materialReceipt->display_transaction_number, $purchaseCsv);
        $this->assertStringContainsString($legacyPurchase->display_transaction_number, $purchaseCsv);
        $this->assertStringContainsString('Material Receipt', $purchaseCsv);
        $this->assertStringContainsString('Legacy Purchase', $purchaseCsv);

        $this->assertStringContainsString('Context', $salesCsv);
        $this->assertStringContainsString($legacySale->display_transaction_number, $salesCsv);
        $this->assertStringContainsString('Legacy Sale', $salesCsv);
        $this->assertStringNotContainsString($materialUsage->display_transaction_number, $salesCsv);
        $this->assertStringNotContainsString('Material Usage', $salesCsv);
    }

    public function test_dashboard_stats_keep_recent_usage_and_purchase_value_context_aware(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['name' => 'Coating']);
        $product = Product::factory()->create([
            'sku' => 'SKU-DB-CTX',
            'item_code_ierp' => 'IERP-DB-CTX',
            'purchase_price' => 9000,
            'selling_price' => 14000,
        ]);

        $materialReceipt = $this->seedReceivedPurchase(
            $user,
            $product,
            'material_receipt',
            'MR.260626.0003',
            'DN-CTX-003',
            'MR-DB-BATCH',
            total: 18000,
        );

        $legacyPurchase = $this->seedReceivedPurchase(
            $user,
            $product,
            'legacy_purchase',
            'PO.260626.0003',
            'PO-CTX-003',
            'PO-DB-BATCH',
            total: 24000,
        );

        $materialUsage = $this->seedSale(
            $user,
            $product,
            SaleTransactionType::MATERIAL_USAGE,
            'MU.260626.0003',
            'REQ-CTX-004',
            team: $team,
            quantity: 2,
        );

        $legacySale = $this->seedSale(
            $user,
            $product,
            SaleTransactionType::SALE,
            'INV.260626.0003',
            'INV-CTX-003',
            quantity: 1,
        );

        $service = app(DashboardStatsService::class);

        $recentReceipts = collect($service->getRecentReceipts(5));
        $recentUsage = collect($service->getRecentMaterialUsage(5));
        $businessStats = $service->getBusinessInsightStats(now()->subDays(29)->startOfDay(), now()->endOfDay());

        $this->assertTrue($recentReceipts->contains(fn (array $row) => $row['receipt_number'] === $materialReceipt->display_transaction_number && $row['context_label'] === 'Material Receipt'));
        $this->assertTrue($recentReceipts->contains(fn (array $row) => $row['receipt_number'] === $legacyPurchase->display_transaction_number && $row['context_label'] === 'Legacy Purchase'));
        $this->assertTrue($recentUsage->contains(fn (array $row) => $row['usage_number'] === $materialUsage->display_transaction_number && $row['team'] === $team->name));
        $this->assertFalse($recentUsage->contains(fn (array $row) => $row['usage_number'] === $legacySale->display_transaction_number));
        $this->assertSame(24000, $businessStats['purchase_total']);
    }

    private function seedUsageProduct(string $sku, string $batchNumber, int $availableQuantity): array
    {
        $product = Product::factory()->create([
            'sku' => $sku,
            'item_code_ierp' => "IERP-{$sku}",
            'purchase_price' => 5000,
            'selling_price' => 7000,
            'quantity' => $availableQuantity,
        ]);

        $batch = Batch::create([
            'product_id' => $product->id,
            'batch_number' => $batchNumber,
            'expiry_date' => now()->addMonths(6)->toDateString(),
            'received_at' => now()->subDay(),
            'storage_location' => 'RM-CTX',
            'unit_cost' => 5000,
            'selling_price' => 7000,
            'quantity' => $availableQuantity,
            'available_quantity' => $availableQuantity,
            'source' => 'purchase',
        ]);

        return [$product, $batch];
    }

    private function seedReceivedPurchase(
        User $user,
        Product $product,
        string $entryContext,
        string $transactionCode,
        string $referenceNumber,
        string $batchNumber,
        int $total = 14000,
    ): Purchase {
        $purchase = Purchase::create([
            'supplier_id' => null,
            'invoice_number' => $referenceNumber,
            'transaction_code' => $transactionCode,
            'purchase_date' => now(),
            'status' => PurchaseStatus::RECEIVED,
            'created_by' => $user->id,
            'entry_context' => $entryContext,
            'proof_image' => $entryContext === 'legacy_purchase' ? 'proofs/existing-proof.jpg' : null,
            'total' => $total,
        ]);

        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'batch_number' => $batchNumber,
            'expiry_date' => now()->addMonths(6)->toDateString(),
            'storage_location' => 'RM-CTX',
            'quantity' => 2,
            'unit_price' => (int) ($total / 2),
            'selling_price' => (int) $product->selling_price,
            'subtotal' => $total,
        ]);

        return $purchase;
    }

    private function seedSale(
        User $user,
        Product $product,
        SaleTransactionType $transactionType,
        string $transactionCode,
        string $referenceNumber,
        ?Team $team = null,
        int $quantity = 1,
    ): Sale {
        $sale = Sale::create([
            'invoice_number' => $referenceNumber,
            'transaction_code' => $transactionCode,
            'transaction_type' => $transactionType,
            'created_by' => $user->id,
            'issued_by' => $user->id,
            'sale_date' => now(),
            'usage_date' => now(),
            'status' => SaleStatus::COMPLETED,
            'subtotal' => $product->selling_price * $quantity,
            'global_discount' => 0,
            'total_discount' => 0,
            'total' => $product->selling_price * $quantity,
            'cash_received' => 0,
            'change' => 0,
            'payment_method' => PaymentMethod::TRANSFER,
            'purpose' => $transactionType === SaleTransactionType::MATERIAL_USAGE ? 'Context usage' : null,
            'team_id' => $team?->id,
            'project' => $team?->name,
            'requested_by' => $transactionType === SaleTransactionType::MATERIAL_USAGE ? 'RNI Team' : null,
        ]);

        $saleItem = SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'cost_price' => (int) $product->purchase_price,
            'total_cost' => (int) $product->purchase_price * $quantity,
            'unit_price' => (int) $product->selling_price,
            'discount' => 0,
            'final_price' => (int) $product->selling_price,
            'subtotal' => (int) $product->selling_price * $quantity,
        ]);

        if ($transactionType === SaleTransactionType::MATERIAL_USAGE) {
            $batch = Batch::firstOrCreate(
                ['product_id' => $product->id, 'batch_number' => "{$transactionCode}-BATCH"],
                [
                    'expiry_date' => now()->addMonths(4)->toDateString(),
                    'received_at' => now()->subDay(),
                    'storage_location' => 'RM-CTX',
                    'unit_cost' => (int) $product->purchase_price,
                    'selling_price' => (int) $product->selling_price,
                    'quantity' => 20,
                    'available_quantity' => 20,
                    'source' => 'purchase',
                ],
            );

            SaleItemBatch::create([
                'sale_item_id' => $saleItem->id,
                'batch_id' => $batch->id,
                'quantity' => $quantity,
                'unit_cost' => (int) $product->purchase_price,
            ]);
        }

        return $sale;
    }

    private function bindRoute(string $routeName): void
    {
        $request = Request::create(route($routeName), 'GET');
        $route = app('router')->getRoutes()->match($request);

        $request->setRouteResolver(static fn () => $route);

        app()->instance('request', $request);
        app('url')->setRequest($request);
    }

    private function exportComponentCsv(string $componentClass, array $parameters = []): TestResponse
    {
        $response = Livewire::test($componentClass, $parameters)->instance()->exportToCsv();

        $this->assertNotFalse($response);

        return TestResponse::fromBaseResponse($response);
    }

    private function downloadedFileContent(TestResponse $response): string
    {
        $file = $response->baseResponse->getFile();

        $this->assertNotNull($file);

        return (string) file_get_contents($file->getPathname());
    }
}
