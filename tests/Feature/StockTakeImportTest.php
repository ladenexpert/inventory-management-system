<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Batch;
use App\Models\FinanceTransaction;
use App\Models\InventoryAdjustment;
use App\Models\Product;
use App\Models\StockTakeSession;
use App\Models\StorageLocation;
use App\Models\Unit;
use App\Models\User;
use App\Services\RolePermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\ReadsDownloadedSpreadsheet;
use Tests\TestCase;

class StockTakeImportTest extends TestCase
{
    use RefreshDatabase;
    use ReadsDownloadedSpreadsheet;

    public function test_stock_take_import_page_explains_existing_batch_requirement(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)
            ->get(route('stock-take.index'))
            ->assertOk()
            ->assertSee('SKU, Batch No, and Counted Qty are required.')
            ->assertSee('does not create new batches');
    }

    public function test_stock_take_template_includes_current_headers(): void
    {
        $admin = User::factory()->create();

        $response = $this->actingAs($admin)->get(route('stock-take.template'));

        $response->assertOk();
        $rows = $this->downloadedSpreadsheetRows($response);

        $this->assertSame([
            'SKU',
            'Item Code',
            'Material',
            'Batch No',
            'Expiry',
            'Storage Location',
            'Counted Qty',
            'Reference Number',
            'Notes',
        ], $rows[0]);
        $this->assertSame('# Required: SKU, Batch No, Counted Qty. Optional: Item Code, Material, Expiry, Storage Location, Reference Number, Notes.', $rows[1][0]);
    }

    public function test_preview_creates_persistent_session_and_accepts_blank_optional_cross_check_fields(): void
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

        $response = $this->actingAs($admin)
            ->post(route('stock-take.preview'), ['file' => $file]);

        $session = StockTakeSession::query()->firstOrFail();

        $response->assertRedirect(route('stock-take.show', $session));
        $this->assertSame('imported', $session->status);
        $this->assertSame(1, $session->row_count);
        $this->assertSame(0, $session->error_count);
        $this->assertDatabaseHas('stock_take_rows', [
            'stock_take_session_id' => $session->id,
            'sku' => $product->sku,
            'item_code' => $product->item_code_ierp,
            'batch_number' => $batch->batch_number,
            'storage_location' => $batch->resolved_storage_location,
            'system_qty' => 10,
            'counted_qty' => 10,
            'variance_qty' => 0,
            'status' => 'imported',
        ]);
    }

    public function test_preview_marks_unmatched_batch_row_as_error_and_blocks_posting(): void
    {
        $admin = User::factory()->create();
        [$product] = $this->seedStockTakeBatch(
            sku: 'SKU-STK-UNMATCH-001',
            itemCode: 'IERP-STK-UNMATCH-001',
            batchNumber: 'STK-UNMATCH-001',
            availableQuantity: 10,
            locationCode: 'RACK-UNMATCH',
        );

        $file = $this->makeCsvFile([
            'sku,item_code,material,batch_no,expiry,storage_location,counted_qty,reference,notes',
            "{$product->sku},,{$product->name},UNKNOWN-BATCH,,RACK-UNMATCH,7,STK-UNMATCH,Unmatched batch",
        ]);

        $this->actingAs($admin)->post(route('stock-take.preview'), ['file' => $file]);

        $session = StockTakeSession::query()->firstOrFail();
        $row = $session->rows()->firstOrFail();

        $this->assertSame('error', $row->status);
        $this->assertSame('Unknown Batch No.', $row->error_message);

        $this->actingAs($admin)
            ->post(route('stock-take.apply', $session))
            ->assertRedirect(route('stock-take.show', $session))
            ->assertSessionHas('error');

        $this->assertSame(0, InventoryAdjustment::count());
    }

    public function test_posting_handles_positive_negative_and_zero_variance_without_finance_side_effects(): void
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

        $this->actingAs($admin)->post(route('stock-take.preview'), ['file' => $file]);
        $session = StockTakeSession::query()->firstOrFail();

        $this->actingAs($admin)
            ->post(route('stock-take.apply', $session))
            ->assertRedirect(route('stock-take.show', $session))
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
        $this->assertDatabaseHas('stock_take_sessions', [
            'id' => $session->id,
            'status' => 'posted',
        ]);
    }

    public function test_posting_is_blocked_when_current_qty_changed_since_review_until_recalculated(): void
    {
        $admin = User::factory()->create();
        [, $batch] = $this->seedStockTakeBatch(
            sku: 'SKU-STK-STALE-001',
            itemCode: 'IERP-STK-STALE-001',
            batchNumber: 'STK-STALE-001',
            availableQuantity: 10,
            locationCode: 'RACK-STALE',
        );

        $file = $this->makeCsvFile([
            'sku,item_code,material,batch_no,expiry,storage_location,counted_qty,reference,notes',
            "SKU-STK-STALE-001,,Material SKU-STK-STALE-001,{$batch->batch_number},{$batch->expiry_date->format('Y-m-d')},RACK-STALE,8,STK-STALE,Stale guard",
        ]);

        $this->actingAs($admin)->post(route('stock-take.preview'), ['file' => $file]);
        $session = StockTakeSession::query()->firstOrFail();

        $batch->update([
            'quantity' => 11,
            'available_quantity' => 11,
        ]);

        $this->actingAs($admin)
            ->post(route('stock-take.apply', $session))
            ->assertRedirect(route('stock-take.show', $session))
            ->assertSessionHas('warning');

        $session->refresh();
        $row = $session->rows()->firstOrFail();

        $this->assertSame('reviewed', $session->status);
        $this->assertSame('stale', $row->status);
        $this->assertSame(0, InventoryAdjustment::count());

        $this->actingAs($admin)
            ->post(route('stock-take.recalculate', $session))
            ->assertRedirect(route('stock-take.show', $session))
            ->assertSessionHas('success');

        $row = $session->fresh()->rows()->firstOrFail();
        $this->assertSame('reviewed', $row->status);
        $this->assertSame(11, $row->system_qty);
        $this->assertSame(-3, $row->variance_qty);
    }

    public function test_duplicate_posting_is_blocked_and_close_locks_the_session(): void
    {
        $admin = User::factory()->create();
        [, $batch] = $this->seedStockTakeBatch(
            sku: 'SKU-STK-CLOSE-001',
            itemCode: 'IERP-STK-CLOSE-001',
            batchNumber: 'STK-CLOSE-001',
            availableQuantity: 10,
            locationCode: 'RACK-CLOSE',
        );

        $file = $this->makeCsvFile([
            'sku,item_code,material,batch_no,expiry,storage_location,counted_qty,reference,notes',
            "SKU-STK-CLOSE-001,,Material SKU-STK-CLOSE-001,{$batch->batch_number},{$batch->expiry_date->format('Y-m-d')},RACK-CLOSE,9,STK-CLOSE,Close flow",
        ]);

        $this->actingAs($admin)->post(route('stock-take.preview'), ['file' => $file]);
        $session = StockTakeSession::query()->firstOrFail();

        $this->actingAs($admin)->post(route('stock-take.apply', $session))->assertSessionHas('success');
        $this->assertSame(1, InventoryAdjustment::count());

        $this->actingAs($admin)
            ->post(route('stock-take.apply', $session))
            ->assertRedirect(route('stock-take.show', $session))
            ->assertSessionHas('error');

        $this->assertSame(1, InventoryAdjustment::count());

        $this->actingAs($admin)
            ->post(route('stock-take.close', $session))
            ->assertRedirect(route('stock-take.show', $session))
            ->assertSessionHas('success');

        $session->refresh();
        $this->assertSame('closed', $session->status);
        $this->assertSame('closed', $session->rows()->firstOrFail()->status);

        $this->actingAs($admin)
            ->post(route('stock-take.recalculate', $session))
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_export_hides_valuation_for_non_admin_but_shows_it_for_authorized_admin(): void
    {
        $admin = User::factory()->create();
        $rmDesk = User::factory()->create(['role' => UserRole::RM_DESK]);
        $this->grantStockTakeViewExportToRmDesk($rmDesk);

        [, $batch] = $this->seedStockTakeBatch(
            sku: 'SKU-STK-EXPORT-001',
            itemCode: 'IERP-STK-EXPORT-001',
            batchNumber: 'STK-EXPORT-001',
            availableQuantity: 10,
            locationCode: 'RACK-EXPORT',
        );

        $file = $this->makeCsvFile([
            'sku,item_code,material,batch_no,expiry,storage_location,counted_qty,reference,notes',
            "SKU-STK-EXPORT-001,,Material SKU-STK-EXPORT-001,{$batch->batch_number},{$batch->expiry_date->format('Y-m-d')},RACK-EXPORT,9,STK-EXPORT,Export privacy",
        ]);

        $this->actingAs($admin)->post(route('stock-take.preview'), ['file' => $file]);
        $session = StockTakeSession::query()->firstOrFail();

        $adminExport = $this->actingAs($admin)->get(route('stock-take.export', ['stockTakeSession' => $session, 'format' => 'csv']));
        $adminExport->assertOk();
        $adminCsv = file_get_contents($adminExport->baseResponse->getFile()->getPathname());
        $this->assertStringContainsString('Unit Cost', $adminCsv);
        $this->assertStringContainsString('Adjustment Value', $adminCsv);
        $this->assertStringContainsString('Average Cost', $adminCsv);

        $rmDeskExport = $this->actingAs($rmDesk)->get(route('stock-take.export', ['stockTakeSession' => $session, 'format' => 'csv']));
        $rmDeskExport->assertOk();
        $rmDeskCsv = file_get_contents($rmDeskExport->baseResponse->getFile()->getPathname());
        $this->assertStringNotContainsString('Unit Cost', $rmDeskCsv);
        $this->assertStringNotContainsString('Adjustment Value', $rmDeskCsv);
        $this->assertStringNotContainsString('Average Cost', $rmDeskCsv);
        $this->assertStringContainsString('Variance Qty', $rmDeskCsv);
    }

    private function grantStockTakeViewExportToRmDesk(User $user): void
    {
        $service = app(RolePermissionService::class);
        $permissions = $service->permissionsForRole($user->role->value);
        $permissions['stock_take']['view'] = true;
        $permissions['stock_take']['export'] = true;

        $service->syncRolePermissions($user->role->value, $permissions);
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
            'purchase_price' => 7000,
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
