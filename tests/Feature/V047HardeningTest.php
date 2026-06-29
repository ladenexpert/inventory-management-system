<?php

namespace Tests\Feature;

use App\DTOs\ProductData;
use App\Enums\PurchaseStatus;
use App\Enums\UserRole;
use App\Models\Batch;
use App\Models\FinanceTransaction;
use App\Models\InventoryAdjustment;
use App\Models\InventoryLog;
use App\Models\PhysicalForm;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\StorageLocation;
use App\Models\Team;
use App\Models\Unit;
use App\Models\User;
use App\Services\DashboardStatsService;
use App\Services\InventoryMovementHistoryService;
use App\Services\ProductService;
use App\Support\RmpTerminology;
use Database\Seeders\PhysicalFormSeeder;
use Database\Seeders\TeamSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\ReadsDownloadedSpreadsheet;
use Tests\TestCase;

class V047HardeningTest extends TestCase
{
    use RefreshDatabase;
    use ReadsDownloadedSpreadsheet;

    public function test_physical_form_master_is_seeded_and_product_links_to_master_data(): void
    {
        $this->seed(PhysicalFormSeeder::class);

        $physicalForm = PhysicalForm::query()->where('code', 'powder')->firstOrFail();
        $product = Product::factory()->create([
            'physical_form' => 'powder',
            'physical_form_id' => $physicalForm->id,
        ]);

        $this->assertSame('Powder', $product->fresh()->physical_form_label);
        $this->assertSame($physicalForm->id, $product->physical_form_id);
    }

    public function test_material_usage_requires_team_and_requested_by_for_new_rni_transactions(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['quantity' => 5, 'purchase_price' => 8000]);

        Batch::create([
            'product_id' => $product->id,
            'batch_number' => 'V047-USAGE-001',
            'expiry_date' => now()->addMonth()->toDateString(),
            'received_at' => now()->subDay(),
            'unit_cost' => 8000,
            'selling_price' => 10000,
            'quantity' => 5,
            'available_quantity' => 5,
            'source' => 'purchase',
        ]);

        $this->actingAs($user)
            ->postJson(route('material-usages.store'), [
                'usage_date' => now()->toDateString(),
                'purpose' => 'Validation check',
                'issued_by' => $user->id,
                'items' => [
                    [
                        'product_id' => $product->id,
                        'quantity' => 1,
                    ],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['team_id', 'requested_by']);
    }

    public function test_material_receipt_without_manual_reference_gets_unique_generated_code(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        $payload = function (string $batchNumber) use ($product) {
            return [
                'context' => 'material_receipt',
                'purchase_date' => now()->toDateString(),
                'items' => [
                    [
                        'product_id' => $product->id,
                        'batch_number' => $batchNumber,
                        'expiry_date' => now()->addMonths(6)->toDateString(),
                        'quantity' => 3,
                        'unit_price' => 5000,
                    ],
                ],
            ];
        };

        $this->actingAs($user)
            ->post(route('purchases.store'), $payload('MR-AUTO-001'))
            ->assertRedirect();

        $this->actingAs($user)
            ->post(route('purchases.store'), $payload('MR-AUTO-002'))
            ->assertRedirect();

        $receipts = Purchase::query()
            ->where('entry_context', 'material_receipt')
            ->get(['transaction_code', 'invoice_number']);

        $this->assertCount(2, $receipts);
        $this->assertTrue($receipts->pluck('transaction_code')->every(fn (?string $value) => is_string($value) && str_starts_with($value, 'MR.')));
        $this->assertSame(2, $receipts->pluck('transaction_code')->unique()->count());
        $this->assertTrue($receipts->pluck('invoice_number')->every(fn ($value) => $value === null));
    }

    public function test_product_stock_adjustment_creates_traceable_adjustment_code_and_links_inventory_logs(): void
    {
        $this->seed(PhysicalFormSeeder::class);

        $user = User::factory()->create();
        $this->actingAs($user);

        $unit = Unit::query()->first() ?? Unit::factory()->create(['name' => 'Kilogram', 'symbol' => 'KG']);
        $product = Product::factory()->create([
            'unit_id' => $unit->id,
            'quantity' => 5,
            'purchase_price' => 6000,
            'selling_price' => 8000,
            'physical_form' => 'powder',
            'physical_form_id' => PhysicalForm::query()->where('code', 'powder')->value('id'),
        ]);

        Batch::create([
            'product_id' => $product->id,
            'batch_number' => 'ADJ-V047-001',
            'expiry_date' => now()->addMonth()->toDateString(),
            'received_at' => now()->subDay(),
            'unit_cost' => 6000,
            'selling_price' => 8000,
            'quantity' => 5,
            'available_quantity' => 5,
            'source' => 'purchase',
        ]);

        app(ProductService::class)->updateProduct($product, ProductData::fromArray([
            'category_id' => $product->category_id,
            'unit_id' => $product->unit_id,
            'supplier_id' => $product->supplier_id,
            'sku' => $product->sku,
            'item_code_ierp' => $product->item_code_ierp,
            'name' => $product->name,
            'physical_form' => 'powder',
            'purchase_price' => 6000,
            'selling_price' => 8000,
            'quantity' => 8,
            'min_stock' => $product->min_stock,
            'is_active' => true,
            'description' => $product->description,
            'notes' => $product->notes,
        ]));

        $adjustment = InventoryAdjustment::query()->where('adjustment_type', 'manual_stock_adjustment')->firstOrFail();

        $this->assertStringStartsWith('ADJ.', $adjustment->transaction_code);
        $this->assertDatabaseHas('inventory_logs', [
            'inventory_adjustment_id' => $adjustment->id,
            'movement_type' => 'adjustment_in',
        ]);
    }

    public function test_stock_take_import_is_admin_only_and_applies_traceable_variance_adjustments(): void
    {
        $this->seed(TeamSeeder::class);

        $admin = User::factory()->create();
        $formulator = User::factory()->create(['role' => UserRole::FORMULATOR]);
        $location = StorageLocation::factory()->create(['code' => 'RACK-A1', 'name' => 'Rack A1']);
        $unit = Unit::factory()->create(['name' => 'Pieces', 'symbol' => 'PCS']);
        $product = Product::factory()->create([
            'item_code_ierp' => 'IERP-STK-001',
            'quantity' => 10,
            'unit_id' => $unit->id,
        ]);

        $batch = Batch::create([
            'product_id' => $product->id,
            'batch_number' => 'STK-BATCH-001',
            'expiry_date' => now()->addMonths(3)->toDateString(),
            'received_at' => now()->subDay(),
            'storage_location' => $location->display_label,
            'storage_location_id' => $location->id,
            'unit_cost' => 7000,
            'selling_price' => 9500,
            'quantity' => 10,
            'available_quantity' => 10,
            'source' => 'purchase',
        ]);

        $file = UploadedFile::fake()->createWithContent('stock-take.csv', implode("\n", [
            'sku,item_code,material,batch_no,expiry,storage_location,counted_qty,reference,notes',
            "\"{$product->sku}\",,{$product->name},STK-BATCH-001,{$batch->expiry_date->format('Y-m-d')},RACK-A1,7,STK-JUN-01,Cycle count",
        ]));

        $this->actingAs($formulator)
            ->post(route('stock-take.preview'), ['file' => $file])
            ->assertForbidden();

        $this->actingAs($admin)
            ->from(route('stock-take.index'))
            ->post(route('stock-take.preview'), ['file' => $file])
            ->assertRedirect(route('stock-take.index'));

        $this->actingAs($admin)
            ->post(route('stock-take.apply'))
            ->assertRedirect();

        $adjustment = InventoryAdjustment::query()->where('adjustment_type', 'stock_take_import')->firstOrFail();

        $this->assertStringStartsWith('STK.', $adjustment->transaction_code);
        $this->assertSame('STK-JUN-01', $adjustment->reference);
        $this->assertSame(7, $batch->fresh()->available_quantity);
        $this->assertDatabaseHas('inventory_logs', [
            'inventory_adjustment_id' => $adjustment->id,
            'movement_type' => 'stock_take_adjustment_out',
        ]);
    }

    public function test_stock_take_template_uses_current_headers(): void
    {
        $admin = User::factory()->create();

        $response = $this->actingAs($admin)->get(route('stock-take.template'));

        $response->assertOk();
        $response->assertDownload('template-stock-take-import.xlsx');

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

    public function test_stock_take_adjustment_stays_out_of_usage_purchase_and_finance_contexts_while_remaining_visible_in_history(): void
    {
        $admin = User::factory()->create();
        $location = StorageLocation::factory()->create(['code' => 'RACK-STK', 'name' => 'Rack STK']);
        $unit = Unit::factory()->create(['name' => 'Pieces', 'symbol' => 'PCS']);
        $product = Product::factory()->create([
            'item_code_ierp' => 'IERP-STK-CTX-001',
            'quantity' => 12,
            'unit_id' => $unit->id,
        ]);

        $batch = Batch::create([
            'product_id' => $product->id,
            'batch_number' => 'STK-CTX-BATCH-001',
            'expiry_date' => now()->addMonths(3)->toDateString(),
            'received_at' => now()->subDay(),
            'storage_location' => $location->display_label,
            'storage_location_id' => $location->id,
            'unit_cost' => 7000,
            'selling_price' => 9500,
            'quantity' => 12,
            'available_quantity' => 12,
            'source' => 'opening_balance',
        ]);

        $file = UploadedFile::fake()->createWithContent('stock-take-context.csv', implode("\n", [
            'sku,item_code,material,batch_no,expiry,storage_location,counted_qty,reference,notes',
            "\"{$product->sku}\",,{$product->name},STK-CTX-BATCH-001,{$batch->expiry_date->format('Y-m-d')},RACK-STK,9,STK-CTX-01,Context isolation",
        ]));

        $this->actingAs($admin)->post(route('stock-take.preview'), ['file' => $file])->assertRedirect();
        $this->actingAs($admin)->post(route('stock-take.apply'))->assertRedirect();

        $adjustment = InventoryAdjustment::query()->where('adjustment_type', 'stock_take_import')->latest('id')->firstOrFail();

        $this->assertStringStartsWith('STK.', $adjustment->transaction_code);
        $this->assertSame('Stock Take Adjustment', $adjustment->context_label);
        $this->assertSame(0, FinanceTransaction::count());
        $this->assertSame(9, $batch->fresh()->available_quantity);

        $historyRows = app(InventoryMovementHistoryService::class)->exportRows();
        $this->assertTrue($historyRows->contains(fn (array $row) => $row['transaction_number'] === $adjustment->transaction_code && $row['transaction_type'] === 'Stock Take Adjustment Out'));

        $dashboard = app(DashboardStatsService::class);
        $this->assertTrue($dashboard->getPurchaseAnalysisRows(now()->subDays(29)->startOfDay(), now()->endOfDay())->isEmpty());
        $this->assertTrue(collect($dashboard->getRecentReceipts(10))->isEmpty());
        $this->assertTrue(collect($dashboard->getRecentMaterialUsage(10))->isEmpty());
        $this->assertSame(0, $dashboard->getBusinessInsightStats(now()->subDays(29)->startOfDay(), now()->endOfDay())['purchase_total']);

        $this->actingAs($admin)
            ->get(route('reports.usage-history'))
            ->assertOk()
            ->assertDontSee($adjustment->transaction_code)
            ->assertDontSee('Context isolation');
    }
}
