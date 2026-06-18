<?php

namespace Tests\Feature;

use App\DTOs\ProductData;
use App\Enums\PurchaseStatus;
use App\Enums\SaleTransactionType;
use App\Models\Batch;
use App\Models\Category;
use App\Models\InventoryLog;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Services\ProductService;
use App\Services\PurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RniRequirementCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_service_persists_physical_form_optional_supplier_and_opening_storage_location(): void
    {
        $category = Category::factory()->create();
        $unit = Unit::factory()->create();
        $supplier = Supplier::factory()->create();
        $service = app(ProductService::class);

        $product = $service->createProduct(ProductData::fromArray([
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'supplier_id' => $supplier->id,
            'name' => 'Lactose Anhydrous',
            'item_code_ierp' => 'IERP-LAC-001',
            'physical_form' => 'powder',
            'purchase_price' => 8000,
            'selling_price' => 10000,
            'quantity' => 12,
            'opening_batch_number' => 'LAC-OPEN-001',
            'opening_expiry_date' => now()->addMonths(8)->toDateString(),
            'opening_storage_location' => 'Room A / Rack 2',
            'min_stock' => 2,
            'is_active' => true,
            'description' => null,
            'notes' => null,
        ]));

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'supplier_id' => $supplier->id,
            'physical_form' => 'powder',
        ]);

        $this->assertDatabaseHas('batches', [
            'product_id' => $product->id,
            'batch_number' => 'LAC-OPEN-001',
            'storage_location' => 'Room A / Rack 2',
        ]);

        $service->updateProduct($product->fresh(), ProductData::fromArray([
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'supplier_id' => null,
            'sku' => $product->sku,
            'name' => 'Lactose Anhydrous',
            'item_code_ierp' => 'IERP-LAC-001',
            'physical_form' => 'granule',
            'purchase_price' => 8000,
            'selling_price' => 10000,
            'quantity' => 12,
            'min_stock' => 2,
            'is_active' => true,
            'description' => null,
            'notes' => 'Updated form',
        ]));

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'supplier_id' => null,
            'physical_form' => 'granule',
        ]);
    }

    public function test_material_receipt_allows_blank_supplier_and_preserves_storage_location_on_receive(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $product = Product::factory()->create([
            'quantity' => 0,
            'purchase_price' => 6000,
            'selling_price' => 9000,
        ]);

        $response = $this->actingAs($user)->post(route('purchases.store'), [
            'context' => 'material_receipt',
            'supplier_id' => '',
            'invoice_number' => 'MR-001',
            'purchase_date' => now()->toDateString(),
            'proof_image' => UploadedFile::fake()->image('receipt-proof.jpg'),
            'items' => [
                [
                    'product_id' => $product->id,
                    'batch_number' => 'MR-BATCH-001',
                    'expiry_date' => now()->addMonths(3)->toDateString(),
                    'storage_location' => 'Lab Shelf 1',
                    'quantity' => 5,
                    'unit_price' => 6500,
                    'selling_price' => 9000,
                ],
            ],
        ]);

        $purchase = Purchase::query()->firstOrFail();

        $response->assertRedirect(route('material-receipts.show', $purchase));
        $this->assertNull($purchase->supplier_id);
        $this->assertDatabaseHas('purchase_items', [
            'purchase_id' => $purchase->id,
            'storage_location' => 'Lab Shelf 1',
        ]);

        app(PurchaseService::class)->markAsReceived($purchase->fresh());

        $this->assertDatabaseHas('batches', [
            'purchase_id' => $purchase->id,
            'batch_number' => 'MR-BATCH-001',
            'storage_location' => 'Lab Shelf 1',
        ]);
    }

    public function test_inventory_movement_history_page_and_filters_work_for_rni_logs(): void
    {
        $admin = User::factory()->create(['name' => 'Admin RNI']);
        $issuer = User::factory()->create(['name' => 'Formulator One']);
        $supplier = Supplier::factory()->create();
        $productA = Product::factory()->create([
            'name' => 'Citric Acid',
            'item_code_ierp' => 'IERP-CA-001',
        ]);
        $productB = Product::factory()->create([
            'name' => 'Magnesium Stearate',
            'item_code_ierp' => 'IERP-MS-001',
        ]);

        $batchA = Batch::create([
            'product_id' => $productA->id,
            'batch_number' => 'CA-LOT-001',
            'expiry_date' => now()->addMonths(4)->toDateString(),
            'received_at' => now()->subDay(),
            'unit_cost' => 7000,
            'selling_price' => 9000,
            'quantity' => 10,
            'available_quantity' => 10,
            'source' => 'purchase',
            'storage_location' => 'Rack A',
        ]);

        $batchB = Batch::create([
            'product_id' => $productB->id,
            'batch_number' => 'MS-LOT-002',
            'expiry_date' => now()->addMonths(6)->toDateString(),
            'received_at' => now()->subDays(2),
            'unit_cost' => 5000,
            'selling_price' => 8000,
            'quantity' => 8,
            'available_quantity' => 6,
            'source' => 'opening_balance',
        ]);

        $purchase = Purchase::create([
            'supplier_id' => $supplier->id,
            'invoice_number' => 'PO-HIST-001',
            'purchase_date' => now(),
            'total' => 70000,
            'status' => PurchaseStatus::RECEIVED,
            'created_by' => $admin->id,
            'proof_image' => 'proofs/hist.jpg',
        ]);

        $purchaseItem = PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $productA->id,
            'batch_number' => $batchA->batch_number,
            'expiry_date' => $batchA->expiry_date,
            'storage_location' => 'Rack A',
            'quantity' => 10,
            'unit_price' => 7000,
            'selling_price' => 9000,
            'subtotal' => 70000,
        ]);

        $usage = Sale::create([
            'invoice_number' => 'MUS-HIST-001',
            'transaction_type' => SaleTransactionType::MATERIAL_USAGE,
            'created_by' => $issuer->id,
            'issued_by' => $issuer->id,
            'sale_date' => now(),
            'usage_date' => now(),
            'status' => 'completed',
            'subtotal' => 0,
            'global_discount' => 0,
            'total_discount' => 0,
            'total' => 0,
            'cash_received' => 0,
            'change' => 0,
            'payment_method' => 'transfer',
            'purpose' => 'Pilot mix',
            'formula' => 'PM-001',
            'project' => 'Pilot',
            'requested_by' => 'QA',
            'notes' => 'Usage note',
        ]);

        InventoryLog::create([
            'product_id' => $productA->id,
            'batch_id' => $batchA->id,
            'purchase_id' => $purchase->id,
            'purchase_item_id' => $purchaseItem->id,
            'movement_type' => 'purchase_receive',
            'quantity' => 10,
            'quantity_before' => 0,
            'quantity_after' => 10,
            'notes' => 'Purchase receive note',
            'created_at' => now()->subHours(4),
            'updated_at' => now()->subHours(4),
        ]);

        InventoryLog::create([
            'product_id' => $productB->id,
            'batch_id' => $batchB->id,
            'sale_id' => $usage->id,
            'movement_type' => 'sale_out',
            'quantity' => -2,
            'quantity_before' => 8,
            'quantity_after' => 6,
            'notes' => 'Usage note',
            'created_at' => now()->subHours(2),
            'updated_at' => now()->subHours(2),
        ]);

        $this->actingAs($admin)
            ->get(route('reports.inventory-movement-history'))
            ->assertOk()
            ->assertSee('Inventory Movement History')
            ->assertSee('Purchase receive note')
            ->assertSee('Usage note');

        $this->actingAs($admin)
            ->get(route('reports.inventory-movement-history', [
                'transaction_type' => 'purchase_receive',
                'rm_code' => 'IERP-CA',
                'user_id' => $admin->id,
                'lot_number' => 'CA-LOT',
                'from_date' => now()->toDateString(),
                'to_date' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('Purchase receive note')
            ->assertDontSee('Usage note');
    }

    public function test_current_inventory_report_and_legacy_null_fields_render_safely(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create(['name' => 'PT Excipient Prima']);

        $richProduct = Product::factory()->create([
            'name' => 'Maltodextrin',
            'item_code_ierp' => 'IERP-MAL-001',
            'physical_form' => 'powder',
            'supplier_id' => $supplier->id,
        ]);

        Batch::create([
            'product_id' => $richProduct->id,
            'batch_number' => 'MAL-001',
            'expiry_date' => now()->addMonths(5)->toDateString(),
            'received_at' => now()->subDay(),
            'unit_cost' => 10000,
            'selling_price' => 12000,
            'quantity' => 15,
            'available_quantity' => 15,
            'source' => 'opening_balance',
            'storage_location' => 'Warehouse 1',
        ]);

        $legacyProduct = Product::factory()->create([
            'name' => 'Legacy RM',
            'item_code_ierp' => 'IERP-LEG-002',
            'physical_form' => null,
            'supplier_id' => null,
        ]);

        Batch::create([
            'product_id' => $legacyProduct->id,
            'batch_number' => 'LEG-NULL-001',
            'expiry_date' => null,
            'received_at' => now()->subDays(2),
            'unit_cost' => 0,
            'selling_price' => 0,
            'quantity' => 3,
            'available_quantity' => 3,
            'source' => 'legacy_sync',
            'storage_location' => null,
        ]);

        $this->actingAs($user)
            ->get(route('reports.inventory'))
            ->assertOk()
            ->assertSee('Physical Form')
            ->assertSee('Storage Location')
            ->assertSee('PT Excipient Prima')
            ->assertSee('Warehouse 1')
            ->assertSee('Legacy RM')
            ->assertSee('LEG-NULL-001');

        $this->actingAs($user)
            ->get(route('batches.index'))
            ->assertOk()
            ->assertSee('Storage Location')
            ->assertSee('Warehouse 1')
            ->assertSee('LEG-NULL-001');
    }
}
