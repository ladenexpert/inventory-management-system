<?php

namespace Tests\Feature;

use App\Enums\PurchaseStatus;
use App\Livewire\Products\ProductForm;
use App\Models\Batch;
use App\Models\Category;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Support\RmpTerminology;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Tests\Concerns\ReadsDownloadedSpreadsheet;
use Tests\TestCase;

class UatRoundTwoRegressionTest extends TestCase
{
    use RefreshDatabase;
    use ReadsDownloadedSpreadsheet;

    public function test_procurement_material_search_includes_active_zero_stock_materials(): void
    {
        $user = User::factory()->create();
        $zeroStock = Product::factory()->create([
            'name' => 'Zero Stock Material',
            'quantity' => 0,
            'is_active' => true,
        ]);
        $inStock = Product::factory()->create([
            'name' => 'In Stock Material',
            'quantity' => 12,
            'is_active' => true,
        ]);
        Product::factory()->create([
            'name' => 'Inactive Material',
            'quantity' => 50,
            'is_active' => false,
        ]);

        $response = $this->actingAs($user)->postJson(route('ajax.products.search'), [
            'scope' => 'procurement',
            'q' => 'Material',
        ]);

        $response->assertOk();
        $response->assertJsonFragment(['id' => $zeroStock->id, 'name' => 'Zero Stock Material']);
        $response->assertJsonFragment(['id' => $inStock->id, 'name' => 'In Stock Material']);
        $response->assertJsonMissing(['name' => 'Inactive Material']);
    }

    public function test_product_edit_form_preloads_category_unit_supplier_and_physical_form(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->create(['name' => 'Binders']);
        $unit = Unit::factory()->create(['name' => 'Kilogram', 'symbol' => 'KG']);
        $supplier = Supplier::factory()->create(['name' => 'PT Preload Supplier']);
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'supplier_id' => $supplier->id,
            'physical_form' => 'powder',
        ]);

        Livewire::actingAs($user);

        Livewire::test(ProductForm::class)
            ->dispatch('edit-product', $product)
            ->assertSet('category_id', $category->id)
            ->assertSet('unit_id', $unit->id)
            ->assertSet('supplier_id', $supplier->id)
            ->assertSet('physical_form', 'powder')
            ->assertSeeHtml('data-initial-label="' . e($category->name) . '"')
            ->assertSeeHtml('data-initial-label="' . e($supplier->name) . '"')
            ->assertSeeHtml('data-initial-label="' . e("{$unit->name} ({$unit->symbol})") . '"');
    }

    public function test_purchase_store_accepts_pdf_attachment(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create(['quantity' => 0]);

        $response = $this->actingAs($user)->post(route('purchases.store'), [
            'supplier_id' => $supplier->id,
            'invoice_number' => 'PO-PDF-001',
            'purchase_date' => now()->toDateString(),
            'proof_image' => UploadedFile::fake()->create('proof.pdf', 100, 'application/pdf'),
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 3,
                    'unit_price' => 15000,
                    'selling_price' => 17000,
                ],
            ],
        ]);

        $purchase = Purchase::firstOrFail();

        $response->assertRedirect(route('purchases.show', $purchase));
        $this->assertStringEndsWith('.pdf', (string) $purchase->proof_image);
    }

    public function test_purchase_receipt_print_page_renders(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create(['name' => 'PT Print Supplier']);
        $product = Product::factory()->create(['name' => 'Print Material']);
        $purchase = Purchase::create([
            'supplier_id' => $supplier->id,
            'invoice_number' => 'PO-PRINT-001',
            'purchase_date' => now(),
            'total' => 45000,
            'status' => PurchaseStatus::RECEIVED,
            'created_by' => $user->id,
        ]);

        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'quantity' => 3,
            'unit_price' => 15000,
            'selling_price' => 18000,
            'subtotal' => 45000,
            'storage_location' => 'Rack A1',
        ]);

        $this->actingAs($user)
            ->get(route('purchases.print', $purchase))
            ->assertOk()
            ->assertSee('Purchase Receipt')
            ->assertSee('PO-PRINT-001')
            ->assertSee('Print Material');
    }

    public function test_sales_print_renders_multiple_items_without_blade_errors(): void
    {
        $user = User::factory()->create();
        $sale = $this->createSaleWithLineCount($user, 2);

        $this->actingAs($user)
            ->get(route('sales.print', $sale))
            ->assertOk()
            ->assertSee($sale->invoice_number)
            ->assertSee('Sale Material 1')
            ->assertSee('Sale Material 2');
    }

    public function test_multi_line_sales_process_successfully_for_one_five_and_ten_items(): void
    {
        $user = User::factory()->create();

        foreach ([1, 5, 10] as $lineCount) {
            $startedAt = microtime(true);
            $sale = $this->createSaleWithLineCount($user, $lineCount);
            $duration = microtime(true) - $startedAt;

            $this->assertSame($lineCount, $sale->items()->count());
            $this->assertLessThan(15, $duration, "Sale with {$lineCount} lines took too long to process.");
        }
    }

    public function test_master_data_template_and_material_import_work(): void
    {
        $user = User::factory()->create();
        Category::factory()->create(['name' => 'Excipients', 'slug' => 'excipients']);
        Unit::factory()->create(['name' => 'Kilogram', 'symbol' => 'KG']);
        $supplier = Supplier::factory()->create(['name' => 'PT Import Supplier']);

        $templateResponse = $this->actingAs($user)->get(route('master-imports.template', 'materials'));
        $templateResponse->assertOk();
        $templateResponse->assertDownload('template-materials.xlsx');

        $rows = $this->downloadedSpreadsheetRows($templateResponse);

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
            'Min Stock',
            RmpTerminology::STATUS,
            'Description',
            RmpTerminology::NOTES,
        ], $rows[0]);

        $file = $this->makeXlsxFile([
            ['SKU', RmpTerminology::ITEM_CODE, RmpTerminology::MATERIAL_NAME, 'Category', RmpTerminology::UNIT, RmpTerminology::PHYSICAL_FORM, 'Supplier', 'Purchase Price', 'Selling Price', 'Min Stock', RmpTerminology::STATUS, 'Description', RmpTerminology::NOTES],
            ['MAT-0001', 'IERP-MAT-0001', 'Imported MCC', 'Excipients', 'KG', 'powder', $supplier->name, '20000', '28000', '4', '1', 'Material import', 'Round 2'],
        ]);

        $response = $this->actingAs($user)->post(route('master-imports.store', 'materials'), [
            'file' => $file,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('import_report', fn ($report) => $report['created_rows'] === 1 && $report['failed_rows'] === 0);
        $this->assertDatabaseHas('products', [
            'sku' => 'MAT-0001',
            'item_code_ierp' => 'IERP-MAT-0001',
            'name' => 'Imported MCC',
            'supplier_id' => $supplier->id,
            'quantity' => 0,
        ]);
    }

    protected function createSaleWithLineCount(User $user, int $lineCount): Sale
    {
        $items = [];

        for ($i = 1; $i <= $lineCount; $i++) {
            $product = Product::factory()->create([
                'name' => "Sale Material {$i}",
                'quantity' => 20,
                'purchase_price' => 10000 + $i,
                'selling_price' => 15000 + $i,
            ]);

            Batch::create([
                'product_id' => $product->id,
                'batch_number' => "SALE-BATCH-{$lineCount}-{$i}",
                'expiry_date' => now()->addMonths(6)->toDateString(),
                'received_at' => now()->subDay(),
                'unit_cost' => 10000 + $i,
                'selling_price' => 15000 + $i,
                'quantity' => 20,
                'available_quantity' => 20,
                'source' => 'purchase',
            ]);

            $items[] = [
                'product_id' => $product->id,
                'quantity' => 1,
                'unit_price' => 15000 + $i,
                'discount' => 0,
            ];
        }

        $response = $this->actingAs($user)->postJson(route('sales.store'), [
            'sale_date' => now()->toDateString(),
            'payment_method' => 'transfer',
            'status' => 'completed',
            'global_discount' => 0,
            'items' => $items,
        ]);

        $response->assertCreated()->assertJsonPath('success', true);

        return Sale::latest('id')->firstOrFail();
    }

    protected function makeXlsxFile(array $rows): UploadedFile
    {
        $directory = storage_path('app/testing-imports');

        if (!File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $path = $directory . DIRECTORY_SEPARATOR . 'master-import-' . uniqid('', true) . '.xlsx';

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
