<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Category;
use App\Models\FinanceTransaction;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Services\DashboardStatsService;
use App\Services\InventoryMovementHistoryService;
use App\Support\RmpTerminology;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\Concerns\ReadsDownloadedSpreadsheet;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProductOpeningStockImportTest extends TestCase
{
    use RefreshDatabase;
    use ReadsDownloadedSpreadsheet;

    public function test_opening_stock_template_can_be_downloaded(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('products.import-opening-stock.template'));

        $response->assertOk();
        $response->assertDownload('template-import-stok-awal.xlsx');

        $rows = $this->downloadedSpreadsheetRows($response);

        $this->assertSame([
            'SKU',
            RmpTerminology::ITEM_CODE,
            RmpTerminology::MATERIAL_NAME,
            'Category',
            RmpTerminology::UNIT,
            RmpTerminology::PHYSICAL_FORM,
            'Supplier',
            'Purchase Price',
            'Selling Price',
            'Opening Qty',
            'Opening Batch No',
            'Opening Expiry Date',
            RmpTerminology::STORAGE_LOCATION,
            'Min Stock',
            RmpTerminology::STATUS,
            'Description',
            RmpTerminology::NOTES,
        ], $rows[0]);
    }

    public function test_it_imports_opening_stock_from_xlsx(): void
    {
        $user = User::factory()->create();
        Category::factory()->create(['name' => 'Medicine', 'slug' => 'medicine']);
        Unit::factory()->create(['name' => 'PCS', 'symbol' => 'PCS']);
        $supplier = Supplier::factory()->create(['name' => 'PT Sample Ingredient']);

        $file = $this->makeXlsxFile([
            ['SKU', RmpTerminology::ITEM_CODE, RmpTerminology::MATERIAL_NAME, 'Category', RmpTerminology::UNIT, RmpTerminology::PHYSICAL_FORM, 'Supplier', 'Purchase Price', 'Selling Price', 'Opening Qty', 'Opening Batch No', 'Opening Expiry Date', RmpTerminology::STORAGE_LOCATION, 'Min Stock', RmpTerminology::STATUS, 'Description', RmpTerminology::NOTES],
            ['PRD-OB-001', 'IERP-OB-001', 'Paracetamol 500mg', 'Medicine', 'PCS', 'powder', $supplier->name, '1200', '1500', '100', 'OB-PARA-001', '2027-01-15', 'Rack A-01', '10', '1', 'Tablet', 'Migrasi awal'],
        ]);

        $response = $this->actingAs($user)->post(route('products.import-opening-stock.store'), [
            'file' => $file,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('import_report', function ($report) {
            return $report['created_rows'] === 1 && $report['failed_rows'] === 0;
        });

        $this->assertDatabaseHas('products', [
            'sku' => 'PRD-OB-001',
            'item_code_ierp' => 'IERP-OB-001',
            'name' => 'Paracetamol 500mg',
            'supplier_id' => $supplier->id,
            'physical_form' => 'powder',
            'quantity' => 100,
        ]);

        $product = Product::where('sku', 'PRD-OB-001')->firstOrFail();

        $this->assertDatabaseHas('batches', [
            'product_id' => $product->id,
            'batch_number' => 'OB-PARA-001',
            'expiry_date' => '2027-01-15 00:00:00',
            'source' => 'opening_balance',
            'available_quantity' => 100,
        ]);
        $this->assertDatabaseHas('storage_locations', [
            'name' => 'Rack A-01',
        ]);
    }

    public function test_it_keeps_legacy_import_template_compatible_without_new_columns(): void
    {
        $user = User::factory()->create();
        Category::factory()->create(['name' => 'Excipient', 'slug' => 'excipient']);
        Unit::factory()->create(['name' => 'KG', 'symbol' => 'KG']);

        $file = $this->makeXlsxFile([
            ['sku', 'item_code_ierp', 'name', 'category', 'unit', 'purchase_price', 'selling_price', 'opening_quantity', 'opening_batch_number'],
            ['PRD-LEG-001', 'IERP-LEG-001', 'Legacy Material', 'Excipient', 'KG', '5000', '7000', '20', 'LEG-001'],
        ]);

        $response = $this->actingAs($user)->post(route('products.import-opening-stock.store'), [
            'file' => $file,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('import_report', fn ($report) => $report['created_rows'] === 1 && $report['failed_rows'] === 0);

        $this->assertDatabaseHas('products', [
            'sku' => 'PRD-LEG-001',
            'physical_form' => null,
            'supplier_id' => null,
        ]);

        $this->assertDatabaseHas('batches', [
            'batch_number' => 'LEG-001',
            'storage_location' => null,
        ]);
    }

    public function test_it_reports_failed_rows_when_category_not_found(): void
    {
        $user = User::factory()->create();
        Unit::factory()->create(['name' => 'PCS', 'symbol' => 'PCS']);

        $file = $this->makeXlsxFile([
            ['sku', 'item_code_ierp', 'name', 'category', 'unit', 'purchase_price', 'selling_price', 'opening_quantity', 'opening_batch_number'],
            ['PRD-OB-404', 'IERP-OB-404', 'Unknown Category Product', 'KategoriTidakAda', 'PCS', '1000', '1500', '20', 'OB-404-001'],
        ]);

        $response = $this->actingAs($user)->post(route('products.import-opening-stock.store'), [
            'file' => $file,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('import_report', function ($report) {
            return $report['created_rows'] === 0
                && $report['failed_rows'] === 1
                && !empty($report['errors']);
        });

        $this->assertDatabaseMissing('products', [
            'sku' => 'PRD-OB-404',
        ]);

        $this->assertSame(0, Product::count());
        $this->assertSame(0, Batch::count());
    }

    public function test_duplicate_item_code_error_uses_current_label(): void
    {
        $user = User::factory()->create();
        Category::factory()->create(['name' => 'Medicine', 'slug' => 'medicine']);
        Unit::factory()->create(['name' => 'PCS', 'symbol' => 'PCS']);
        Product::factory()->create(['item_code_ierp' => 'IERP-DUP-001']);

        $file = $this->makeXlsxFile([
            ['SKU', RmpTerminology::ITEM_CODE, RmpTerminology::MATERIAL_NAME, 'Category', RmpTerminology::UNIT, 'Purchase Price', 'Selling Price', 'Opening Qty'],
            ['PRD-DUP-001', 'IERP-DUP-001', 'Duplicate Code Material', 'Medicine', 'PCS', '1000', '1500', '20'],
        ]);

        $response = $this->actingAs($user)->post(route('products.import-opening-stock.store'), [
            'file' => $file,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('import_report', function ($report) {
            return $report['failed_rows'] === 1
                && str_contains($report['errors'][0]['message'] ?? '', RmpTerminology::ITEM_CODE)
                && !str_contains($report['errors'][0]['message'] ?? '', 'Item Code IERP');
        });
    }

    public function test_opening_stock_import_stays_out_of_purchase_and_finance_contexts_while_remaining_visible_in_inventory_history(): void
    {
        $user = User::factory()->create();
        Category::factory()->create(['name' => 'Excipient', 'slug' => 'excipient']);
        Unit::factory()->create(['name' => 'KG', 'symbol' => 'KG']);

        $file = $this->makeXlsxFile([
            ['SKU', RmpTerminology::ITEM_CODE, RmpTerminology::MATERIAL_NAME, 'Category', RmpTerminology::UNIT, 'Purchase Price', 'Selling Price', 'Opening Qty', 'Opening Batch No', 'Opening Expiry Date', RmpTerminology::STORAGE_LOCATION],
            ['PRD-CTX-OS-001', 'IERP-CTX-OS-001', 'Opening Stock Context Material', 'Excipient', 'KG', '5000', '7000', '15', 'OS-CTX-001', '2027-01-15', 'Rack OS-01'],
        ]);

        $this->actingAs($user)->post(route('products.import-opening-stock.store'), [
            'file' => $file,
        ])->assertRedirect();

        $product = Product::query()->where('sku', 'PRD-CTX-OS-001')->firstOrFail();

        $this->assertSame(0, Purchase::count());
        $this->assertSame(0, FinanceTransaction::count());
        $this->assertDatabaseHas('batches', [
            'product_id' => $product->id,
            'batch_number' => 'OS-CTX-001',
            'source' => 'opening_balance',
            'available_quantity' => 15,
        ]);

        $historyRows = app(InventoryMovementHistoryService::class)->exportRows();
        $this->assertTrue($historyRows->contains(fn (array $row) => $row['transaction_type'] === 'Opening Stock' && $row['lot_number'] === 'OS-CTX-001'));

        $historyExport = $this->actingAs($user)
            ->get(route('reports.inventory-movement-history.export', ['format' => 'csv']))
            ->assertOk();

        $historyExport->assertDownload('inventory_movement_history_' . now()->format('Y_m_d') . '.csv');
        $this->assertStringContainsString('Opening Stock', (string) file_get_contents($historyExport->baseResponse->getFile()->getPathname()));

        $dashboard = app(DashboardStatsService::class);
        $this->assertFalse(collect($dashboard->getRecentReceipts(10))->contains(fn (array $row) => ($row['receipt_number'] ?? null) === 'OS-CTX-001'));
        $this->assertSame(0, $dashboard->getBusinessInsightStats(now()->subDays(29)->startOfDay(), now()->endOfDay())['purchase_total']);
        $this->assertTrue(
            $dashboard->getPurchaseAnalysisRows(now()->subDays(29)->startOfDay(), now()->endOfDay())->isEmpty()
        );
    }

    public function test_opening_stock_import_routes_remain_protected_by_opening_stock_permission(): void
    {
        $user = User::factory()->create(['role' => \App\Enums\UserRole::FORMULATOR]);

        $this->actingAs($user)->get(route('products.import-opening-stock'))->assertForbidden();
        $this->actingAs($user)->get(route('products.import-opening-stock.template'))->assertForbidden();
        $this->actingAs($user)->post(route('products.import-opening-stock.store'), [])->assertForbidden();
    }

    private function makeXlsxFile(array $rows): UploadedFile
    {
        $directory = storage_path('app/testing-imports');

        if (!File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $path = $directory . DIRECTORY_SEPARATOR . 'opening-stock-' . uniqid('', true) . '.xlsx';

        $writer = new Writer();
        $writer->openToFile($path);

        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }

        $writer->close();

        return new UploadedFile(
            $path,
            basename($path),
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }
}
