<?php

namespace Tests\Unit;

use App\Models\Batch;
use App\Models\Product;
use App\Services\FefoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FefoServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_fefo_recommendation_ignores_expired_batches(): void
    {
        $product = Product::factory()->create(['quantity' => 0]);
        $service = app(FefoService::class);

        Batch::create([
            'product_id' => $product->id,
            'batch_number' => 'FEFO-OLD',
            'expiry_date' => now()->subDay()->toDateString(),
            'received_at' => now()->subDays(2),
            'unit_cost' => 1000,
            'selling_price' => 2000,
            'quantity' => 4,
            'available_quantity' => 4,
            'source' => 'purchase',
        ]);

        Batch::create([
            'product_id' => $product->id,
            'batch_number' => 'FEFO-SOON',
            'expiry_date' => now()->addDays(3)->toDateString(),
            'received_at' => now()->subDay(),
            'unit_cost' => 1000,
            'selling_price' => 2000,
            'quantity' => 5,
            'available_quantity' => 5,
            'source' => 'purchase',
        ]);

        $recommendations = $service->recommendBatches($product, 3);

        $this->assertSame(['FEFO-SOON'], $recommendations->pluck('batch.batch_number')->all());
        $this->assertSame([3], $recommendations->pluck('quantity')->all());
    }

    public function test_manual_batch_validation_rejects_depleted_batch_selection(): void
    {
        $product = Product::factory()->create(['quantity' => 0]);
        $service = app(FefoService::class);

        $depletedBatch = Batch::create([
            'product_id' => $product->id,
            'batch_number' => 'FEFO-DEP',
            'expiry_date' => now()->addDays(5)->toDateString(),
            'received_at' => now(),
            'unit_cost' => 1000,
            'selling_price' => 2000,
            'quantity' => 5,
            'available_quantity' => 0,
            'source' => 'purchase',
        ]);

        $this->expectExceptionMessage("Batch 'FEFO-DEP' with status 'Depleted' cannot be allocated for product '{$product->name}'.");

        $service->validateBatchSelection($product, [
            ['batch_id' => $depletedBatch->id, 'quantity' => 1],
        ]);
    }
}
