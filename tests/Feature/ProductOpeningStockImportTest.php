<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProductOpeningStockImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_opening_stock_template_can_be_downloaded(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('products.import-opening-stock.template'));

        $response->assertOk();
        $response->assertDownload('template-import-stok-awal.xlsx');
    }

    public function test_it_imports_opening_stock_from_xlsx(): void
    {
        $user = User::factory()->create();
        Category::factory()->create(['name' => 'Medicine', 'slug' => 'medicine']);
        Unit::factory()->create(['name' => 'PCS', 'symbol' => 'PCS']);

        $file = $this->makeXlsxFile([
            ['sku', 'item_code_ierp', 'name', 'category', 'unit', 'purchase_price', 'selling_price', 'opening_quantity', 'opening_batch_number', 'opening_expiry_date', 'min_stock', 'is_active', 'description', 'notes'],
            ['PRD-OB-001', 'IERP-OB-001', 'Paracetamol 500mg', 'Medicine', 'PCS', '1200', '1500', '100', 'OB-PARA-001', '2027-01-15', '10', '1', 'Tablet', 'Migrasi awal'],
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
