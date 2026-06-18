<?php

namespace Tests\Unit;

use App\Enums\BatchStatus;
use App\Models\Batch;
use App\Models\Product;
use App\Models\Setting;
use App\Services\BatchPolicyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BatchPolicyServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_detects_near_expiry_and_expired_batches(): void
    {
        Setting::set(BatchPolicyService::NEAR_EXPIRY_SETTING_KEY, '10');

        $product = Product::factory()->create(['quantity' => 0]);
        $service = app(BatchPolicyService::class);

        $nearExpiry = Batch::create([
            'product_id' => $product->id,
            'batch_number' => 'POLICY-NEAR',
            'expiry_date' => now()->addDays(7)->toDateString(),
            'received_at' => now(),
            'unit_cost' => 1000,
            'selling_price' => 2000,
            'quantity' => 3,
            'available_quantity' => 3,
            'source' => 'purchase',
        ]);

        $expired = Batch::create([
            'product_id' => $product->id,
            'batch_number' => 'POLICY-EXPIRED',
            'expiry_date' => now()->subDay()->toDateString(),
            'received_at' => now(),
            'unit_cost' => 1000,
            'selling_price' => 2000,
            'quantity' => 4,
            'available_quantity' => 4,
            'source' => 'purchase',
        ]);

        $this->assertTrue($service->isNearExpiry($nearExpiry));
        $this->assertSame(BatchStatus::NEAR_EXPIRY, $service->getStatus($nearExpiry));
        $this->assertTrue($service->isExpired($expired));
        $this->assertFalse($service->canBeSold($expired));
        $this->assertSame(BatchStatus::EXPIRED, $service->getStatus($expired));
    }

    public function test_zero_cost_batch_is_valid_and_inventory_value_is_calculated_from_available_quantity(): void
    {
        Setting::set(BatchPolicyService::NEAR_EXPIRY_SETTING_KEY, '30');

        $product = Product::factory()->create(['quantity' => 0]);
        $service = app(BatchPolicyService::class);

        $zeroCostBatch = Batch::create([
            'product_id' => $product->id,
            'batch_number' => 'POLICY-ZERO',
            'expiry_date' => now()->addDays(20)->toDateString(),
            'received_at' => now(),
            'unit_cost' => 0,
            'selling_price' => 2000,
            'quantity' => 7,
            'available_quantity' => 5,
            'source' => 'purchase',
        ]);

        $valuedBatch = Batch::create([
            'product_id' => $product->id,
            'batch_number' => 'POLICY-VALUE',
            'expiry_date' => now()->addDays(20)->toDateString(),
            'received_at' => now(),
            'unit_cost' => 1200,
            'selling_price' => 2000,
            'quantity' => 7,
            'available_quantity' => 5,
            'source' => 'purchase',
        ]);

        $this->assertTrue($service->canBeSold($zeroCostBatch));
        $this->assertSame(0, $service->inventoryValue($zeroCostBatch));
        $this->assertSame(6000, $service->inventoryValue($valuedBatch));
        $this->assertSame(BatchStatus::NEAR_EXPIRY, $service->getStatus($valuedBatch));
    }
}
