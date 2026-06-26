<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\PurchaseStatus;
use App\Enums\SaleStatus;
use App\Enums\SaleTransactionType;
use App\Livewire\MaterialUsages\MaterialUsageTable;
use App\Livewire\Purchases\PurchaseTable;
use App\Models\Batch;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleItemBatch;
use App\Models\Team;
use App\Models\User;
use App\Support\TransactionContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Tests\Concerns\ReadsDownloadedSpreadsheet;
use Tests\TestCase;

class OperationLineExportTest extends TestCase
{
    use ReadsDownloadedSpreadsheet;
    use RefreshDatabase;

    public function test_material_receipt_selected_header_export_expands_all_line_items(): void
    {
        $user = User::factory()->create();
        $productA = Product::factory()->create(['sku' => 'MR-SKU-001', 'item_code_ierp' => 'MR-ITEM-001']);
        $productB = Product::factory()->create(['sku' => 'MR-SKU-002', 'item_code_ierp' => 'MR-ITEM-002']);
        $receipt = $this->createPurchaseWithItems($user, TransactionContext::MATERIAL_RECEIPT, 'MR.260626.0101', [
            ['product' => $productA, 'batch' => 'MR-LINE-001', 'quantity' => 2, 'unit_price' => 4000, 'subtotal' => 8000],
            ['product' => $productB, 'batch' => 'MR-LINE-002', 'quantity' => 3, 'unit_price' => 5000, 'subtotal' => 15000],
        ]);

        $this->actingAs($user);
        $response = TestResponse::fromBaseResponse(
            Livewire::test(PurchaseTable::class, ['context' => TransactionContext::MATERIAL_RECEIPT])
                ->set('checkboxValues', [$receipt->id])
                ->instance()
                ->exportToXLS(true)
        );

        $response->assertDownload('material_receipt_lines_' . now()->format('Y_m_d') . '.xlsx');

        $rows = $this->downloadedSpreadsheetRows($response);
        $transactionColumn = $this->columnIndex($rows[0], 'Transaction Number');
        $batchColumn = $this->columnIndex($rows[0], 'Batch No');

        $this->assertCount(3, $rows);
        $this->assertSame($receipt->display_transaction_number, $rows[1][$transactionColumn]);
        $this->assertSame($receipt->display_transaction_number, $rows[2][$transactionColumn]);
        $this->assertSame('MR-LINE-001', $rows[1][$batchColumn]);
        $this->assertSame('MR-LINE-002', $rows[2][$batchColumn]);
    }

    public function test_material_usage_exports_expand_selected_headers_and_follow_filtered_scope_without_selection(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['name' => 'Dispensing']);
        $productA = Product::factory()->create(['sku' => 'MU-SKU-001', 'item_code_ierp' => 'MU-ITEM-001', 'purchase_price' => 6000]);
        $productB = Product::factory()->create(['sku' => 'MU-SKU-002', 'item_code_ierp' => 'MU-ITEM-002', 'purchase_price' => 7000]);

        $selectedUsage = $this->createUsageWithItems($user, $team, 'MU.260626.0201', '=REQ-CTX-0201', [
            ['product' => $productA, 'batch' => 'MU-LINE-001', 'quantity' => 2, 'unit_cost' => 6000],
            ['product' => $productB, 'batch' => 'MU-LINE-002', 'quantity' => 1, 'unit_cost' => 7000],
        ]);

        $otherUsage = $this->createUsageWithItems($user, $team, 'MU.260626.0202', 'REQ-CTX-0202', [
            ['product' => $productA, 'batch' => 'MU-LINE-003', 'quantity' => 1, 'unit_cost' => 6000],
        ]);

        $this->actingAs($user);

        $selectedResponse = TestResponse::fromBaseResponse(
            Livewire::test(MaterialUsageTable::class)
                ->set('checkboxValues', [$selectedUsage->id])
                ->instance()
                ->exportToXLS(true)
        );

        $selectedResponse->assertDownload('material_usage_lines_' . now()->format('Y_m_d') . '.xlsx');

        $selectedRows = $this->downloadedSpreadsheetRows($selectedResponse);
        $transactionColumn = $this->columnIndex($selectedRows[0], 'Transaction Number');
        $requestedByColumn = $this->columnIndex($selectedRows[0], 'Requested By');

        $this->assertCount(3, $selectedRows);
        $this->assertSame($selectedUsage->display_transaction_number, $selectedRows[1][$transactionColumn]);
        $this->assertSame($selectedUsage->display_transaction_number, $selectedRows[2][$transactionColumn]);
        $this->assertSame("'=REQ-CTX-0201", $selectedRows[1][$requestedByColumn]);

        $filteredResponse = TestResponse::fromBaseResponse(
            Livewire::test(MaterialUsageTable::class)
                ->set('search', $otherUsage->display_transaction_number)
                ->instance()
                ->exportToCsv()
        );

        $filteredCsv = $this->downloadedFileContent($filteredResponse);

        $this->assertStringContainsString($otherUsage->display_transaction_number, $filteredCsv);
        $this->assertStringNotContainsString($selectedUsage->display_transaction_number, $filteredCsv);
    }

    public function test_empty_operation_export_returns_valid_headers_only_workbook(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = TestResponse::fromBaseResponse(
            Livewire::test(MaterialUsageTable::class)
                ->set('search', 'NO-MATCH-ROWS')
                ->instance()
                ->exportToXLS()
        );

        $rows = $this->downloadedSpreadsheetRows($response);

        $response->assertDownload('material_usage_lines_' . now()->format('Y_m_d') . '.xlsx');
        $this->assertCount(1, $rows);
        $this->assertSame('Transaction Number', $rows[0][0]);
    }

    private function createPurchaseWithItems(User $user, string $context, string $transactionCode, array $items): Purchase
    {
        $purchase = Purchase::create([
            'supplier_id' => null,
            'invoice_number' => $context === TransactionContext::MATERIAL_RECEIPT ? 'MR-REF-001' : 'PO-REF-001',
            'transaction_code' => $transactionCode,
            'purchase_date' => now(),
            'status' => PurchaseStatus::RECEIVED,
            'created_by' => $user->id,
            'entry_context' => $context,
            'total' => collect($items)->sum('subtotal'),
        ]);

        foreach ($items as $item) {
            PurchaseItem::create([
                'purchase_id' => $purchase->id,
                'product_id' => $item['product']->id,
                'batch_number' => $item['batch'],
                'expiry_date' => now()->addMonths(6)->toDateString(),
                'storage_location' => 'RM Export Rack',
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'selling_price' => (int) $item['product']->selling_price,
                'subtotal' => $item['subtotal'],
            ]);
        }

        return $purchase->fresh();
    }

    private function createUsageWithItems(User $user, Team $team, string $transactionCode, string $requestedBy, array $items): Sale
    {
        $sale = Sale::create([
            'invoice_number' => $requestedBy,
            'transaction_code' => $transactionCode,
            'transaction_type' => SaleTransactionType::MATERIAL_USAGE,
            'created_by' => $user->id,
            'issued_by' => $user->id,
            'sale_date' => now(),
            'usage_date' => now(),
            'status' => SaleStatus::COMPLETED,
            'subtotal' => collect($items)->sum(fn (array $item) => $item['quantity'] * $item['unit_cost']),
            'global_discount' => 0,
            'total_discount' => 0,
            'total' => collect($items)->sum(fn (array $item) => $item['quantity'] * $item['unit_cost']),
            'cash_received' => 0,
            'change' => 0,
            'payment_method' => PaymentMethod::TRANSFER,
            'purpose' => 'Usage export test',
            'team_id' => $team->id,
            'project' => $team->name,
            'requested_by' => $requestedBy,
        ]);

        foreach ($items as $item) {
            $batch = Batch::create([
                'product_id' => $item['product']->id,
                'batch_number' => $item['batch'],
                'expiry_date' => now()->addMonths(4)->toDateString(),
                'received_at' => now()->subDay(),
                'storage_location' => 'RM Export Rack',
                'unit_cost' => $item['unit_cost'],
                'selling_price' => (int) $item['product']->selling_price,
                'quantity' => 10,
                'available_quantity' => 10,
                'source' => 'purchase',
            ]);

            $saleItem = SaleItem::create([
                'sale_id' => $sale->id,
                'product_id' => $item['product']->id,
                'quantity' => $item['quantity'],
                'cost_price' => $item['unit_cost'],
                'total_cost' => $item['quantity'] * $item['unit_cost'],
                'unit_price' => $item['unit_cost'],
                'discount' => 0,
                'final_price' => $item['unit_cost'],
                'subtotal' => $item['quantity'] * $item['unit_cost'],
            ]);

            SaleItemBatch::create([
                'sale_item_id' => $saleItem->id,
                'batch_id' => $batch->id,
                'quantity' => $item['quantity'],
                'unit_cost' => $item['unit_cost'],
            ]);
        }

        return $sale->fresh();
    }

    private function columnIndex(array $headerRow, string $columnLabel): int
    {
        $index = array_search($columnLabel, $headerRow, true);

        $this->assertNotFalse($index, "Unable to find export column [{$columnLabel}].");

        return $index;
    }

    private function downloadedFileContent(TestResponse $response): string
    {
        $file = $response->baseResponse->getFile();

        $this->assertNotNull($file);

        return (string) file_get_contents($file->getPathname());
    }
}
