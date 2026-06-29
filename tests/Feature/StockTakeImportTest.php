<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\FinanceTransaction;
use App\Models\InventoryAdjustment;
use App\Models\Product;
use App\Models\StorageLocation;
use App\Models\Unit;
use App\Models\User;
use App\Support\RmpTerminology;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\ReadsDownloadedSpreadsheet;
use Tests\TestCase;

class StockTakeImportTest extends TestCase
{
    use RefreshDatabase;
    use ReadsDownloadedSpreadsheet;

    public function test_stock_take_import_page_explains_sku_and_batch_requirement(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)
            ->get(route('stock-take.index'))
            ->assertOk()
            ->assertSee('SKU, Batch No, and Counted Qty are required.')
            ->assertSee('Item Code, Material, Expiry, Storage Location, Reference Number, and Notes are optional.');
    }

    public function test_stock_take_template_includes_sku_first_and_marks_required_columns(): void
    {
        $admin = User::factory()->create();

        $response = $this->actingAs($admin)->get(route('stock-take.template'));

        $response->assertOk();
        $rows = $this->downloadedSpreadsheetRows($response);

        $this->assertSame([
            RmpTerminology::SKU,
            RmpTerminology::ITEM_CODE,
            'Material',
            RmpTerminology::BATCH_NO,
            'Expiry',
            RmpTerminology::STORAGE_LOCATION,
            RmpTerminology::COUNTED_QTY,
            RmpTerminology::REFERENCE_NUMBER,
            RmpTerminology::NOTES,
        ], $rows[0]);
        $this->assertSame('# Required: SKU, Batch No, Counted Qty. Optional: Item Code, Material, Expiry, Storage Location, Reference Number, Notes.', $rows[1][0]);
    }

    public function test_stock_take_preview_accepts_blank_item_code_and_blank_storage_location_when_sku_and_batch_are_valid(): void
    {
        $admin = User::factory()->create();
        [$product, $batch] = $this->seedStockTakeBatch(
            sku: 'SKU-STK-OPTIONAL-001',
            itemCode: 'IERP-STK-OPTIONAL-001',
            batchNumber: 'STK-OPTIONAL-001',
            availableQuantity: 10,
            locationCode: 'RACK-OPTIONAL',
        );

        $file = $this->makeCsvFile([
            'sku,item_code,material,batch_no,expiry,storage_location,counted_qty,reference,notes',
            "{$product->sku},,{$product->name},{$batch->batch_number},,,10,STK-OPTIONAL,Blank item code and storage location are allowed",
        ]);

        $this->actingAs($admin)
            ->from(route('stock-take.index'))
            ->post(route('stock-take.preview'), ['file' => $file])
            ->assertRedirect(route('stock-take.index'))
            ->assertSessionHas('stock_take_preview', function (array $preview) use ($product, $batch) {
                return $preview['errors'] === []
                    && count($preview['rows']) === 1
                    && $preview['rows'][0]['sku'] === $product->sku
                    && $preview['rows'][0]['item_code'] === $product->item_code_ierp
                    && $preview['rows'][0]['batch_number'] === $batch->batch_number
                    && $preview['rows'][0]['storage_location'] === $batch->resolved_storage_location
                    && $preview['rows'][0]['variance'] === 0;
            });
    }

    public function test_stock_take_preview_accepts_blank_expiry_when_sku_and_batch_are_valid(): void
    {
        $admin = User::factory()->create();
        [$product, $batch] = $this->seedStockTakeBatch(
            sku: 'SKU-STK-EXP-BLANK-001',
            itemCode: 'IERP-STK-EXP-BLANK-001',
            batchNumber: 'STK-EXP-BLANK-001',
            availableQuantity: 10,
            locationCode: 'RACK-EXP-BLANK',
        );

        $file = $this->makeCsvFile([
            'sku,item_code,material,batch_no,expiry,storage_location,counted_qty,reference,notes',
            "{$product->sku},,{$product->name},{$batch->batch_number},,RACK-EXP-BLANK,10,STK-EXP-BLANK,Blank expiry passes",
        ]);

        $this->actingAs($admin)
            ->post(route('stock-take.preview'), ['file' => $file])
            ->assertRedirect()
            ->assertSessionHas('stock_take_preview', fn (array $preview) => $preview['errors'] === [] && ($preview['rows'][0]['expiry_date'] ?? null) === $batch->expiry_date->format('Y-m-d'));
    }

    public function test_stock_take_preview_accepts_matching_expiry_when_provided(): void
    {
        $admin = User::factory()->create();
        [$product, $batch] = $this->seedStockTakeBatch(
            sku: 'SKU-STK-EXP-MATCH-001',
            itemCode: 'IERP-STK-EXP-MATCH-001',
            batchNumber: 'STK-EXP-MATCH-001',
            availableQuantity: 10,
            locationCode: 'RACK-EXP-MATCH',
        );

        $file = $this->makeCsvFile([
            'sku,item_code,material,batch_no,expiry,storage_location,counted_qty,reference,notes',
            "{$product->sku},,{$product->name},{$batch->batch_number},{$batch->expiry_date->format('Y-m-d')},,10,STK-EXP-MATCH,Matching expiry passes",
        ]);

        $this->actingAs($admin)
            ->post(route('stock-take.preview'), ['file' => $file])
            ->assertRedirect()
            ->assertSessionHas('stock_take_preview', fn (array $preview) => $preview['errors'] === [] && ($preview['rows'][0]['expiry_date'] ?? null) === $batch->expiry_date->format('Y-m-d'));
    }

    public function test_stock_take_preview_rejects_mismatched_expiry(): void
    {
        $admin = User::factory()->create();
        [$product, $batch] = $this->seedStockTakeBatch(
            sku: 'SKU-STK-EXP-ERR-001',
            itemCode: 'IERP-STK-EXP-ERR-001',
            batchNumber: 'STK-EXP-ERR-001',
            availableQuantity: 10,
            locationCode: 'RACK-EXP-ERR',
        );

        $file = $this->makeCsvFile([
            'sku,item_code,material,batch_no,expiry,storage_location,counted_qty,reference,notes',
            "{$product->sku},,{$product->name},{$batch->batch_number}," . now()->addYear()->format('Y-m-d') . ",,10,STK-EXP-ERR,Mismatched expiry fails",
        ]);

        $this->actingAs($admin)
            ->post(route('stock-take.preview'), ['file' => $file])
            ->assertRedirect()
            ->assertSessionHas('stock_take_preview', function (array $preview) use ($batch) {
                return ($preview['errors'][0]['message'] ?? null) === "Batch No '{$batch->batch_number}' expiry does not match the current batch record.";
            });
    }

    public function test_stock_take_preview_accepts_matching_storage_location_when_provided(): void
    {
        $admin = User::factory()->create();
        [$product, $batch] = $this->seedStockTakeBatch(
            sku: 'SKU-STK-LOC-001',
            itemCode: 'IERP-STK-LOC-001',
            batchNumber: 'STK-LOC-001',
            availableQuantity: 10,
            locationCode: 'RACK-MATCH',
        );

        $file = $this->makeCsvFile([
            'sku,item_code,material,batch_no,expiry,storage_location,counted_qty,reference,notes',
            "{$product->sku},,{$product->name},{$batch->batch_number},,RACK-MATCH,10,STK-LOC-MATCH,Matching location passes",
        ]);

        $this->actingAs($admin)
            ->post(route('stock-take.preview'), ['file' => $file])
            ->assertRedirect()
            ->assertSessionHas('stock_take_preview', fn (array $preview) => $preview['errors'] === [] && ($preview['rows'][0]['storage_location'] ?? null) === $batch->resolved_storage_location);
    }

    public function test_stock_take_preview_rejects_mismatched_storage_location(): void
    {
        $admin = User::factory()->create();
        [$product, $batch] = $this->seedStockTakeBatch(
            sku: 'SKU-STK-LOC-ERR-001',
            itemCode: 'IERP-STK-LOC-ERR-001',
            batchNumber: 'STK-LOC-ERR-001',
            availableQuantity: 10,
            locationCode: 'RACK-REAL',
        );

        StorageLocation::factory()->create([
            'code' => 'RACK-WRONG',
            'name' => 'Rack Wrong',
        ]);

        $file = $this->makeCsvFile([
            'sku,item_code,material,batch_no,expiry,storage_location,counted_qty,reference,notes',
            "{$product->sku},,{$product->name},{$batch->batch_number},,RACK-WRONG,10,STK-LOC-ERR,Mismatched location fails",
        ]);

        $this->actingAs($admin)
            ->post(route('stock-take.preview'), ['file' => $file])
            ->assertRedirect()
            ->assertSessionHas('stock_take_preview', function (array $preview) use ($batch) {
                return ($preview['errors'][0]['message'] ?? null) === "Batch No '{$batch->batch_number}' storage location does not match the current batch record.";
            });
    }

    public function test_stock_take_preview_requires_sku(): void
    {
        $admin = User::factory()->create();
        [, $batch] = $this->seedStockTakeBatch(
            sku: 'SKU-STK-MISSING-001',
            itemCode: 'IERP-STK-MISSING-001',
            batchNumber: 'STK-MISSING-001',
            availableQuantity: 10,
            locationCode: 'RACK-MISSING-SKU',
        );

        $file = $this->makeCsvFile([
            'sku,item_code,material,batch_no,expiry,storage_location,counted_qty,reference,notes',
            ",IERP-STK-MISSING-001,Missing SKU Material,{$batch->batch_number},{$batch->expiry_date->format('Y-m-d')},RACK-MISSING-SKU,10,STK-MISSING-SKU,Missing SKU",
        ]);

        $this->actingAs($admin)
            ->post(route('stock-take.preview'), ['file' => $file])
            ->assertRedirect()
            ->assertSessionHas('stock_take_preview', function (array $preview) {
                return ($preview['errors'][0]['message'] ?? null) === 'SKU is required.';
            });
    }

    public function test_stock_take_preview_rejects_unknown_sku(): void
    {
        $admin = User::factory()->create();
        [, $batch] = $this->seedStockTakeBatch(
            sku: 'SKU-STK-KNOWN-001',
            itemCode: 'IERP-STK-KNOWN-001',
            batchNumber: 'STK-KNOWN-001',
            availableQuantity: 10,
            locationCode: 'RACK-UNKNOWN-SKU',
        );

        $file = $this->makeCsvFile([
            'sku,item_code,material,batch_no,expiry,storage_location,counted_qty,reference,notes',
            "SKU-STK-UNKNOWN-404,,Unknown SKU Material,{$batch->batch_number},{$batch->expiry_date->format('Y-m-d')},RACK-UNKNOWN-SKU,10,STK-UNKNOWN-SKU,Unknown SKU",
        ]);

        $this->actingAs($admin)
            ->post(route('stock-take.preview'), ['file' => $file])
            ->assertRedirect()
            ->assertSessionHas('stock_take_preview', function (array $preview) {
                return ($preview['errors'][0]['message'] ?? null) === 'Unknown SKU.';
            });
    }

    public function test_stock_take_preview_requires_batch_number(): void
    {
        $admin = User::factory()->create();
        [$product, $batch] = $this->seedStockTakeBatch(
            sku: 'SKU-STK-MISSING-BATCH-001',
            itemCode: 'IERP-STK-MISSING-BATCH-001',
            batchNumber: 'STK-MISSING-BATCH-001',
            availableQuantity: 10,
            locationCode: 'RACK-MISSING-BATCH',
        );

        $file = $this->makeCsvFile([
            'sku,item_code,material,batch_no,expiry,storage_location,counted_qty,reference,notes',
            "{$product->sku},,{$product->name},,{$batch->expiry_date->format('Y-m-d')},RACK-MISSING-BATCH,10,STK-MISSING-BATCH,Missing Batch No",
        ]);

        $this->actingAs($admin)
            ->post(route('stock-take.preview'), ['file' => $file])
            ->assertRedirect()
            ->assertSessionHas('stock_take_preview', function (array $preview) {
                return ($preview['errors'][0]['message'] ?? null) === 'Batch No is required.';
            });
    }

    public function test_stock_take_preview_requires_counted_qty(): void
    {
        $admin = User::factory()->create();
        [$product, $batch] = $this->seedStockTakeBatch(
            sku: 'SKU-STK-MISSING-QTY-001',
            itemCode: 'IERP-STK-MISSING-QTY-001',
            batchNumber: 'STK-MISSING-QTY-001',
            availableQuantity: 10,
            locationCode: 'RACK-MISSING-QTY',
        );

        $file = $this->makeCsvFile([
            'sku,item_code,material,batch_no,expiry,storage_location,counted_qty,reference,notes',
            "{$product->sku},,{$product->name},{$batch->batch_number},,, ,STK-MISSING-QTY,Missing counted qty",
        ]);

        $this->actingAs($admin)
            ->post(route('stock-take.preview'), ['file' => $file])
            ->assertRedirect()
            ->assertSessionHas('stock_take_preview', function (array $preview) {
                return ($preview['errors'][0]['message'] ?? null) === 'Counted Qty is required.';
            });
    }

    public function test_stock_take_preview_and_apply_handle_positive_negative_and_zero_variance_correctly(): void
    {
        $admin = User::factory()->create();
        [$positiveProduct, $positiveBatch] = $this->seedStockTakeBatch(
            sku: 'SKU-STK-POS-001',
            itemCode: 'IERP-STK-POS-001',
            batchNumber: 'STK-POS-001',
            availableQuantity: 10,
            locationCode: 'RACK-POS',
        );
        [$negativeProduct, $negativeBatch] = $this->seedStockTakeBatch(
            sku: 'SKU-STK-NEG-001',
            itemCode: 'IERP-STK-NEG-001',
            batchNumber: 'STK-NEG-001',
            availableQuantity: 10,
            locationCode: 'RACK-NEG',
        );
        [$zeroProduct, $zeroBatch] = $this->seedStockTakeBatch(
            sku: 'SKU-STK-ZERO-001',
            itemCode: null,
            batchNumber: 'STK-ZERO-001',
            availableQuantity: 10,
            locationCode: 'RACK-ZERO',
        );

        $file = $this->makeCsvFile([
            'sku,item_code,material,batch_no,expiry,storage_location,counted_qty,reference,notes',
            "{$positiveProduct->sku},{$positiveProduct->item_code_ierp},{$positiveProduct->name},{$positiveBatch->batch_number},{$positiveBatch->expiry_date->format('Y-m-d')},RACK-POS,12,STK-POS,Positive variance",
            "{$negativeProduct->sku},,{$negativeProduct->name},{$negativeBatch->batch_number},{$negativeBatch->expiry_date->format('Y-m-d')},RACK-NEG,7,STK-NEG,Negative variance",
            "{$zeroProduct->sku},,{$zeroProduct->name},{$zeroBatch->batch_number},{$zeroBatch->expiry_date->format('Y-m-d')},RACK-ZERO,10,STK-ZERO,Zero variance",
        ]);

        $this->actingAs($admin)
            ->post(route('stock-take.preview'), ['file' => $file])
            ->assertRedirect()
            ->assertSessionHas('stock_take_preview', function (array $preview) {
                return $preview['errors'] === []
                    && $preview['summary']['valid_rows'] === 3
                    && $preview['summary']['adjustment_rows'] === 2
                    && $preview['rows'][0]['variance'] === 2
                    && $preview['rows'][1]['variance'] === -3
                    && $preview['rows'][2]['variance'] === 0;
            });

        $this->actingAs($admin)
            ->post(route('stock-take.apply'))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(12, $positiveBatch->fresh()->available_quantity);
        $this->assertSame(7, $negativeBatch->fresh()->available_quantity);
        $this->assertSame(10, $zeroBatch->fresh()->available_quantity);

        $adjustments = InventoryAdjustment::query()
            ->where('adjustment_type', 'stock_take_import')
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $adjustments);
        $this->assertTrue($adjustments->pluck('transaction_code')->every(fn (?string $code) => is_string($code) && str_starts_with($code, 'STK.')));
        $this->assertSame(['in', 'out'], $adjustments->pluck('direction')->sort()->values()->all());
        $this->assertSame(0, FinanceTransaction::count());
    }

    private function seedStockTakeBatch(
        string $sku,
        ?string $itemCode,
        string $batchNumber,
        int $availableQuantity,
        string $locationCode,
    ): array {
        $location = StorageLocation::factory()->create([
            'code' => $locationCode,
            'name' => str_replace('-', ' ', $locationCode),
        ]);
        $unit = Unit::query()->firstOrCreate(
            ['symbol' => 'PCS'],
            ['name' => 'Pieces']
        );
        $product = Product::factory()->create([
            'sku' => $sku,
            'item_code_ierp' => $itemCode,
            'name' => "Material {$sku}",
            'quantity' => $availableQuantity,
            'unit_id' => $unit->id,
        ]);
        $batch = Batch::create([
            'product_id' => $product->id,
            'batch_number' => $batchNumber,
            'expiry_date' => now()->addMonths(3)->toDateString(),
            'received_at' => now()->subDay(),
            'storage_location' => $location->display_label,
            'storage_location_id' => $location->id,
            'unit_cost' => 7000,
            'selling_price' => 9000,
            'quantity' => $availableQuantity,
            'available_quantity' => $availableQuantity,
            'source' => 'opening_balance',
        ]);

        return [$product, $batch];
    }

    private function makeCsvFile(array $rows): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('stock-take.csv', implode("\n", $rows));
    }
}
