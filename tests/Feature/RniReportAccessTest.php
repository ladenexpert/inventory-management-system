<?php

namespace Tests\Feature;

use App\Enums\SaleTransactionType;
use App\Models\Batch;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleItemBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RniReportAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_rni_report_pages_render_with_inventory_and_usage_data(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'name' => 'Lactose Monohydrate',
            'quantity' => 12,
            'purchase_price' => 8000,
        ]);

        $batch = Batch::create([
            'product_id' => $product->id,
            'batch_number' => 'LAC-001',
            'expiry_date' => now()->addDays(12)->toDateString(),
            'received_at' => now()->subDays(2),
            'unit_cost' => 8000,
            'selling_price' => 11000,
            'quantity' => 12,
            'available_quantity' => 10,
            'source' => 'purchase',
        ]);

        $usage = Sale::create([
            'invoice_number' => 'MUS.260618.0001',
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
            'purpose' => 'Blend validation',
            'formula' => 'BLD-002',
            'project' => 'Pilot',
            'requested_by' => 'QA Team',
            'notes' => 'Report seed',
        ]);

        $saleItem = SaleItem::create([
            'sale_id' => $usage->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'cost_price' => 8000,
            'total_cost' => 16000,
            'unit_price' => 8000,
            'discount' => 0,
            'final_price' => 8000,
            'subtotal' => 16000,
        ]);

        SaleItemBatch::create([
            'sale_item_id' => $saleItem->id,
            'batch_id' => $batch->id,
            'quantity' => 2,
            'unit_cost' => 8000,
        ]);

        $this->actingAs($user)
            ->get(route('reports.inventory'))
            ->assertOk()
            ->assertSee('Inventory & Expiry Monitoring')
            ->assertSee('Lactose Monohydrate')
            ->assertSee('LAC-001');

        $this->actingAs($user)
            ->get(route('reports.usage-history'))
            ->assertOk()
            ->assertSee('Usage Report')
            ->assertSee('Blend validation')
            ->assertSee('MUS.260618.0001');

        $this->actingAs($user)
            ->get(route('reports.expiry'))
            ->assertOk()
            ->assertSee('Inventory & Expiry Monitoring')
            ->assertSee('LAC-001');

        $this->actingAs($user)
            ->get(route('reports.stock-movement-classification'))
            ->assertOk()
            ->assertSee('Stock Movement Classification')
            ->assertSee('Fast Moving');
    }
}
