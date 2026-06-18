<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\FinanceTransaction;
use App\Models\InventoryLog;
use App\Models\Product;
use App\Models\SaleItemBatch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalesWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_completed_pos_sale_creates_stock_allocations_and_finance_income(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'name' => 'Vitamin C Bottle',
            'quantity' => 8,
            'purchase_price' => 12000,
            'selling_price' => 18000,
        ]);

        Batch::create([
            'product_id' => $product->id,
            'batch_number' => 'POS-001',
            'expiry_date' => now()->addMonths(6)->toDateString(),
            'received_at' => now()->subDay(),
            'unit_cost' => 12000,
            'selling_price' => 18000,
            'quantity' => 8,
            'available_quantity' => 8,
            'source' => 'purchase',
        ]);

        $response = $this->actingAs($user)->postJson(route('sales.store'), [
            'sale_date' => now()->toDateString(),
            'payment_method' => 'transfer',
            'status' => 'completed',
            'global_discount' => 0,
            'notes' => 'POS regression check',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 3,
                    'unit_price' => 18000,
                    'discount' => 1000,
                ],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('redirect_url', route('sales.show', 1))
            ->assertJsonPath('print_url', route('sales.print', 1));

        $this->assertDatabaseHas('sales', [
            'id' => 1,
            'transaction_type' => 'sale',
            'status' => 'completed',
        ]);
        $this->assertSame(1, SaleItemBatch::count());
        $this->assertSame(1, InventoryLog::where('movement_type', 'sale_out')->count());
        $this->assertSame(1, FinanceTransaction::count());
        $this->assertSame(5, (int) Batch::where('batch_number', 'POS-001')->value('available_quantity'));
        $this->assertSame(5, $product->fresh()->quantity);
    }
}
