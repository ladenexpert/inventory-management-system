<?php

namespace Tests\Feature;

use App\Enums\SaleTransactionType;
use App\Models\Batch;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
