<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Batch;
use App\Models\Product;
use App\Models\User;
use App\Services\DashboardStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DashboardBatchAlertTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_batch_alert_stats_and_urgent_batches_are_calculated_correctly(): void
    {
        $product = Product::factory()->create(['quantity' => 0]);

        Batch::create([
            'product_id' => $product->id,
            'batch_number' => 'EXP-001',
            'expiry_date' => now()->subDay()->toDateString(),
            'received_at' => now()->subDays(10),
            'unit_cost' => 10000,
            'selling_price' => 15000,
            'quantity' => 5,
            'available_quantity' => 5,
            'source' => 'purchase',
        ]);

        Batch::create([
            'product_id' => $product->id,
            'batch_number' => 'NEAR-001',
            'expiry_date' => now()->addDays(7)->toDateString(),
            'received_at' => now()->subDays(5),
            'unit_cost' => 10000,
            'selling_price' => 15000,
            'quantity' => 4,
            'available_quantity' => 4,
            'source' => 'purchase',
        ]);

        Batch::create([
            'product_id' => $product->id,
            'batch_number' => 'SAFE-001',
            'expiry_date' => now()->addDays(60)->toDateString(),
            'received_at' => now()->subDays(2),
            'unit_cost' => 10000,
            'selling_price' => 15000,
            'quantity' => 3,
            'available_quantity' => 3,
            'source' => 'purchase',
        ]);

        Batch::create([
            'product_id' => $product->id,
            'batch_number' => 'EMPTY-001',
            'expiry_date' => now()->addDays(3)->toDateString(),
            'received_at' => now()->subDays(1),
            'unit_cost' => 10000,
            'selling_price' => 15000,
            'quantity' => 2,
            'available_quantity' => 0,
            'source' => 'purchase',
        ]);

        $service = app(DashboardStatsService::class);
        $stats = $service->getBatchAlertStats();
        $urgent = $service->getUrgentBatches(10);

        $this->assertSame(1, $stats['expired_count']);
        $this->assertSame(1, $stats['near_expiry_count']);
        $this->assertSame(['EXP-001', 'NEAR-001'], array_column($urgent, 'batch_number'));
        $this->assertSame(['expired', 'near_expiry'], array_column($urgent, 'status'));
    }

    public function test_batch_monitoring_page_can_be_rendered(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('batches.index'));

        $response->assertOk();
        $response->assertSee('Batch Monitoring');
    }
}
