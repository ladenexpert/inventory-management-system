<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Batch;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Enums\SaleTransactionType;
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
            'unit_cost' => 0,
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
        $valuation = $service->getInventoryValuation();

        $this->assertSame(1, $stats['expired_count']);
        $this->assertSame(1, $stats['near_expiry_count']);
        $this->assertSame(1, $stats['depleted_count']);
        $this->assertSame(1, $stats['zero_cost_count']);
        $this->assertSame(['EXP-001', 'NEAR-001'], array_column($urgent, 'batch_number'));
        $this->assertSame(['expired', 'near_expiry'], array_column($urgent, 'status'));
        $this->assertSame(90000, $valuation['cost_value']);
    }

    public function test_batch_monitoring_page_can_be_rendered(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('batches.index'));

        $response->assertOk();
        $response->assertSee('Batch Monitoring');
    }

    public function test_rni_dashboard_metrics_include_recent_material_usage(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'name' => 'Microcrystalline Cellulose',
            'quantity' => 4,
            'min_stock' => 5,
            'is_active' => true,
        ]);

        Batch::create([
            'product_id' => $product->id,
            'batch_number' => 'MCC-001',
            'expiry_date' => now()->addDays(4)->toDateString(),
            'received_at' => now()->subDay(),
            'unit_cost' => 0,
            'selling_price' => 10000,
            'quantity' => 4,
            'available_quantity' => 4,
            'source' => 'purchase',
        ]);

        $usage = Sale::create([
            'transaction_code' => 'MU.260618.0002',
            'invoice_number' => 'REQ-260618-0002',
            'transaction_type' => SaleTransactionType::MATERIAL_USAGE,
            'created_by' => $user->id,
            'issued_by' => $user->id,
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
            'purpose' => 'Compression test',
            'formula' => 'CMP-001',
            'project' => 'Pilot',
            'requested_by' => 'R&D',
            'notes' => null,
        ]);

        SaleItem::create([
            'sale_id' => $usage->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'cost_price' => 0,
            'total_cost' => 0,
            'unit_price' => 0,
            'discount' => 0,
            'final_price' => 0,
            'subtotal' => 0,
        ]);

        $service = app(DashboardStatsService::class);

        $overview = $service->getRniOverviewStats();
        $recentUsage = $service->getRecentMaterialUsage();

        $this->assertSame(1, $overview['total_rm']);
        $this->assertSame(1, $overview['total_batch']);
        $this->assertSame(1, $overview['low_stock']);
        $this->assertSame(1, $overview['near_expiry']);
        $this->assertSame(1, $overview['zero_cost_batch']);
        $this->assertSame('Compression test', $recentUsage[0]['purpose']);
        $this->assertSame('MU.260618.0002', $recentUsage[0]['usage_number']);
    }
}
