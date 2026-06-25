<?php

namespace Tests\Feature;

use App\DTOs\SaleData;
use App\DTOs\SaleItemData;
use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Enums\SaleTransactionType;
use App\Models\Batch;
use App\Models\FinanceTransaction;
use App\Models\InventoryLog;
use App\Models\Product;
use App\Models\SaleItemBatch;
use App\Models\User;
use App\Services\BatchService;
use App\Services\FinanceTransactionService;
use App\Services\SaleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class MaterialUsageWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_material_usage_creation_stores_metadata_and_deducts_stock(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'name' => 'Citric Acid',
            'quantity' => 10,
            'purchase_price' => 12000,
        ]);

        Batch::create([
            'product_id' => $product->id,
            'batch_number' => 'CA-001',
            'expiry_date' => now()->addDays(15)->toDateString(),
            'received_at' => now()->subDays(2),
            'unit_cost' => 12000,
            'selling_price' => 15000,
            'quantity' => 10,
            'available_quantity' => 10,
            'source' => 'purchase',
        ]);

        $response = $this->actingAs($user)->postJson(route('material-usages.store'), [
            'usage_date' => now()->toDateString(),
            'purpose' => 'Pilot capsule run',
            'formula' => 'CAP-001',
            'project' => 'RNI Pilot',
            'requested_by' => 'Dr. Maya',
            'issued_by' => $user->id,
            'notes' => 'First issuance for pilot',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 4,
                    'unit_price' => 12000,
                    'discount' => 0,
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.transaction_type', SaleTransactionType::MATERIAL_USAGE->value)
            ->assertJsonPath('data.purpose', 'Pilot capsule run');

        $this->assertDatabaseHas('sales', [
            'transaction_type' => SaleTransactionType::MATERIAL_USAGE->value,
            'purpose' => 'Pilot capsule run',
            'formula' => 'CAP-001',
            'project' => 'RNI Pilot',
            'requested_by' => 'Dr. Maya',
            'issued_by' => $user->id,
        ]);
        $this->assertSame(1, SaleItemBatch::count());
        $this->assertSame(1, InventoryLog::where('movement_type', 'sale_out')->count());
        $this->assertSame(0, FinanceTransaction::count());

        $this->assertSame(6, (int) Batch::where('batch_number', 'CA-001')->value('available_quantity'));
        $this->assertSame(6, $product->fresh()->quantity);
    }

    public function test_material_usage_uses_fefo_for_batch_allocation(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'name' => 'Magnesium Stearate',
            'quantity' => 9,
            'purchase_price' => 10000,
        ]);

        Batch::create([
            'product_id' => $product->id,
            'batch_number' => 'MS-EARLY',
            'expiry_date' => now()->addDays(5)->toDateString(),
            'received_at' => now()->subDays(3),
            'unit_cost' => 10000,
            'selling_price' => 14000,
            'quantity' => 3,
            'available_quantity' => 3,
            'source' => 'purchase',
        ]);

        Batch::create([
            'product_id' => $product->id,
            'batch_number' => 'MS-LATE',
            'expiry_date' => now()->addDays(30)->toDateString(),
            'received_at' => now()->subDay(),
            'unit_cost' => 10000,
            'selling_price' => 14000,
            'quantity' => 6,
            'available_quantity' => 6,
            'source' => 'purchase',
        ]);

        $response = $this->actingAs($user)->postJson(route('material-usages.store'), [
            'usage_date' => now()->toDateString(),
            'purpose' => 'Granulation trial',
            'issued_by' => $user->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 5,
                    'unit_price' => 10000,
                    'discount' => 0,
                ],
            ],
        ]);

        $response->assertCreated();

        $usageId = $response->json('data.id');
        $usage = \App\Models\Sale::with('items.saleItemBatches.batch')->findOrFail($usageId);
        $allocations = $usage->items->first()->saleItemBatches;

        $this->assertSame(['MS-EARLY', 'MS-LATE'], $allocations->pluck('batch.batch_number')->all());
        $this->assertSame([3, 2], $allocations->pluck('quantity')->all());
        $this->assertSame(0, (int) Batch::where('batch_number', 'MS-EARLY')->value('available_quantity'));
        $this->assertSame(4, (int) Batch::where('batch_number', 'MS-LATE')->value('available_quantity'));
    }

    public function test_material_usage_validation_failure_returns_json_errors_without_creating_records(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'quantity' => 10,
            'purchase_price' => 5000,
        ]);

        Batch::create([
            'product_id' => $product->id,
            'batch_number' => 'VAL-001',
            'expiry_date' => now()->addDays(10)->toDateString(),
            'received_at' => now()->subDay(),
            'unit_cost' => 5000,
            'selling_price' => 7000,
            'quantity' => 10,
            'available_quantity' => 10,
            'source' => 'purchase',
        ]);

        $response = $this->actingAs($user)
            ->withHeader('Accept', 'application/json')
            ->postJson(route('material-usages.store'), [
                'usage_date' => now()->toDateString(),
                'purpose' => '',
                'issued_by' => $user->id,
                'items' => [
                    [
                        'product_id' => $product->id,
                        'quantity' => 0,
                        'unit_price' => 5000,
                        'discount' => 0,
                    ],
                ],
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['purpose', 'items.0.quantity']);

        $this->assertSame(0, \App\Models\Sale::count());
        $this->assertSame(10, (int) Batch::where('batch_number', 'VAL-001')->value('available_quantity'));
        $this->assertSame(0, InventoryLog::count());
        $this->assertSame(0, FinanceTransaction::count());
    }

    public function test_material_usage_standard_form_submission_redirects_to_detail_page(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'quantity' => 6,
            'purchase_price' => 8000,
        ]);

        Batch::create([
            'product_id' => $product->id,
            'batch_number' => 'REDIR-001',
            'expiry_date' => now()->addDays(20)->toDateString(),
            'received_at' => now()->subDay(),
            'unit_cost' => 8000,
            'selling_price' => 9500,
            'quantity' => 6,
            'available_quantity' => 6,
            'source' => 'purchase',
        ]);

        $response = $this->actingAs($user)->post(route('material-usages.store'), [
            'usage_date' => now()->toDateString(),
            'purpose' => 'Redirect check',
            'issued_by' => $user->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'unit_price' => 8000,
                    'discount' => 0,
                ],
            ],
        ]);

        $usage = \App\Models\Sale::query()->latest('id')->firstOrFail();

        $response->assertRedirect(route('material-usages.show', $usage));
        $this->assertSame(SaleTransactionType::MATERIAL_USAGE, $usage->transaction_type);
        $this->assertSame(0, FinanceTransaction::count());
    }

    public function test_material_usage_uses_server_side_cost_snapshot_when_client_unit_price_is_missing(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'quantity' => 5,
            'purchase_price' => 9100,
        ]);

        Batch::create([
            'product_id' => $product->id,
            'batch_number' => 'SERVER-COST-001',
            'expiry_date' => now()->addDays(25)->toDateString(),
            'received_at' => now()->subDay(),
            'unit_cost' => 9100,
            'selling_price' => 11000,
            'quantity' => 5,
            'available_quantity' => 5,
            'source' => 'purchase',
        ]);

        $response = $this->actingAs($user)->postJson(route('material-usages.store'), [
            'usage_date' => now()->toDateString(),
            'purpose' => 'Server cost snapshot',
            'issued_by' => $user->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'discount' => 0,
                ],
            ],
        ]);

        $response->assertCreated();

        $usageItem = \App\Models\SaleItem::query()->latest('id')->firstOrFail();

        $this->assertSame(9100, $usageItem->unit_price);
        $this->assertSame(9100, $usageItem->cost_price);
        $this->assertSame(18200, $usageItem->total_cost);
    }

    public function test_sale_service_retries_when_generated_usage_number_collides(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'quantity' => 8,
            'purchase_price' => 7000,
        ]);

        Batch::create([
            'product_id' => $product->id,
            'batch_number' => 'RETRY-BATCH-001',
            'expiry_date' => now()->addDays(40)->toDateString(),
            'received_at' => now()->subDay(),
            'unit_cost' => 7000,
            'selling_price' => 9000,
            'quantity' => 8,
            'available_quantity' => 8,
            'source' => 'purchase',
        ]);

        \App\Models\Sale::create([
            'invoice_number' => 'MUS.COLLIDE.0001',
            'transaction_type' => SaleTransactionType::MATERIAL_USAGE,
            'created_by' => $user->id,
            'issued_by' => $user->id,
            'sale_date' => now(),
            'usage_date' => now(),
            'status' => SaleStatus::COMPLETED,
            'subtotal' => 0,
            'global_discount' => 0,
            'total_discount' => 0,
            'total' => 0,
            'cash_received' => 0,
            'change' => 0,
            'payment_method' => PaymentMethod::TRANSFER,
            'purpose' => 'Existing reference',
        ]);

        $service = Mockery::mock(SaleService::class, [
            app(FinanceTransactionService::class),
            app(BatchService::class),
        ])->makePartial()->shouldAllowMockingProtectedMethods();

        $service->shouldReceive('generateReferenceNumber')
            ->times(3)
            ->andReturn('MUS.COLLIDE.0001', 'MUS.COLLIDE.0001', 'MUS.COLLIDE.0002');

        $usage = $service->createSale(new SaleData(
            sale_date: now(),
            payment_method: PaymentMethod::TRANSFER,
            created_by: $user->id,
            items: [
                new SaleItemData(
                    product_id: $product->id,
                    quantity: 2,
                    unit_price: 0,
                    discount: 0,
                ),
            ],
            transaction_type: SaleTransactionType::MATERIAL_USAGE,
            status: SaleStatus::COMPLETED,
            usage_date: now(),
            purpose: 'Collision retry',
            issued_by: $user->id,
        ));

        $this->assertSame('MUS.COLLIDE.0002', $usage->invoice_number);
        $this->assertSame(6, $product->fresh()->quantity);
    }
}
