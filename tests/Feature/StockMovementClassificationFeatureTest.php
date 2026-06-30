<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Enums\SaleTransactionType;
use App\Models\Batch;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Services\StockMovementClassificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockMovementClassificationFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_classification_logic_applies_thresholds_zero_stock_exclusion_and_no_usage_basis(): void
    {
        $fast = $this->seedMaterial('Fast Material', 20, 90, 90, 1000);
        $slow = $this->seedMaterial('Slow Material', 18, 181, 181, 1200);
        $dead = $this->seedMaterial('Dead Material', 14, 366, 366, 1300);
        $exactOneEighty = $this->seedMaterial('Exact 180 Material', 12, 180, 180, 900);
        $exactThreeSixtyFive = $this->seedMaterial('Exact 365 Material', 11, 365, 365, 950);
        $noUsageDead = $this->seedMaterial('No Usage Dead', 8, null, 400, 800);
        $noUsageRecent = $this->seedMaterial('No Usage Recent', 7, null, 20, 700);
        $this->seedMaterial('Zero Stock Material', 0, 400, 400, 650);

        $service = app(StockMovementClassificationService::class);
        $records = $service->records()->keyBy('product_name');
        $summary = $service->summary();

        $this->assertSame(StockMovementClassificationService::FAST_MOVING, $records[$fast->name]['classification']);
        $this->assertSame(StockMovementClassificationService::SLOW_MOVING, $records[$slow->name]['classification']);
        $this->assertSame(StockMovementClassificationService::DEAD_STOCK, $records[$dead->name]['classification']);
        $this->assertSame(StockMovementClassificationService::NORMAL_UNCLASSIFIED, $records[$exactOneEighty->name]['classification']);
        $this->assertSame(StockMovementClassificationService::SLOW_MOVING, $records[$exactThreeSixtyFive->name]['classification']);
        $this->assertSame(StockMovementClassificationService::DEAD_STOCK, $records[$noUsageDead->name]['classification']);
        $this->assertSame(StockMovementClassificationService::NORMAL_UNCLASSIFIED, $records[$noUsageRecent->name]['classification']);
        $this->assertFalse($records->has('Zero Stock Material'));

        $this->assertSame(1, $summary['fast_moving']);
        $this->assertSame(2, $summary['slow_moving']);
        $this->assertSame(2, $summary['dead_stock']);
    }

    public function test_stock_movement_classification_report_renders_for_report_users(): void
    {
        $user = User::factory()->create();
        $this->seedMaterial('Rendered Material', 6, 45, 45, 1100);

        $this->actingAs($user)
            ->get(route('reports.stock-movement-classification'))
            ->assertOk()
            ->assertSee('Stock Movement Classification')
            ->assertSee('RNI Material Usage / internal outbound movement only')
            ->assertSee('Legacy sales remain part of Sales Analysis')
            ->assertSee('Rendered Material');
    }

    private function seedMaterial(string $name, int $stock, ?int $usageDaysAgo, int $firstStockDaysAgo, int $unitCost): Product
    {
        $product = Product::factory()->create([
            'name' => $name,
            'quantity' => $stock,
            'purchase_price' => $unitCost,
            'selling_price' => $unitCost + 250,
            'is_active' => true,
        ]);

        Batch::create([
            'product_id' => $product->id,
            'batch_number' => str($name)->slug()->upper() . '-BATCH',
            'expiry_date' => now()->addDays(120)->toDateString(),
            'received_at' => now()->subDays($firstStockDaysAgo),
            'unit_cost' => $unitCost,
            'selling_price' => $unitCost + 250,
            'quantity' => max($stock, 1),
            'available_quantity' => $stock,
            'source' => 'purchase',
        ]);

        if ($usageDaysAgo !== null) {
            $usage = Sale::create([
                'invoice_number' => 'MUS.' . strtoupper(substr(md5($name), 0, 8)),
                'transaction_type' => SaleTransactionType::MATERIAL_USAGE,
                'created_by' => User::factory()->create()->id,
                'issued_by' => User::factory()->create()->id,
                'sale_date' => now()->subDays($usageDaysAgo),
                'usage_date' => now()->subDays($usageDaysAgo),
                'status' => SaleStatus::COMPLETED,
                'subtotal' => $unitCost,
                'global_discount' => 0,
                'total_discount' => 0,
                'total' => $unitCost,
                'cash_received' => 0,
                'change' => 0,
                'payment_method' => PaymentMethod::TRANSFER,
                'purpose' => 'Classification seed',
            ]);

            SaleItem::create([
                'sale_id' => $usage->id,
                'product_id' => $product->id,
                'quantity' => 1,
                'cost_price' => $unitCost,
                'total_cost' => $unitCost,
                'unit_price' => $unitCost,
                'discount' => 0,
                'final_price' => $unitCost,
                'subtotal' => $unitCost,
            ]);
        }

        return $product;
    }
}
