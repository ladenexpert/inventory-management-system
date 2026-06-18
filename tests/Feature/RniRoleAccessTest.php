<?php

namespace Tests\Feature;

use App\Enums\SaleStatus;
use App\Enums\SaleTransactionType;
use App\Models\Batch;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleItemBatch;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RniRoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_formulator_can_access_usage_and_inventory_but_not_admin_pages(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::FORMULATOR,
        ]);

        $this->actingAs($user)->get(route('material-usages.index'))->assertOk();
        $this->actingAs($user)->get(route('products.index'))->assertOk();
        $this->actingAs($user)->get(route('reports.inventory'))->assertOk();

        $this->actingAs($user)->get(route('users.index'))->assertForbidden();
        $this->actingAs($user)->get(route('material-receipts.index'))->assertForbidden();
        $this->actingAs($user)->get(route('finance.transactions.index'))->assertForbidden();
    }

    public function test_formulator_usage_history_shows_only_their_own_usage(): void
    {
        $formulator = User::factory()->create([
            'role' => UserRole::FORMULATOR,
        ]);
        $otherUser = User::factory()->create([
            'role' => UserRole::FORMULATOR,
        ]);
        $product = Product::factory()->create([
            'quantity' => 10,
        ]);
        $batch = Batch::create([
            'product_id' => $product->id,
            'batch_number' => 'OWN-001',
            'expiry_date' => now()->addDays(14)->toDateString(),
            'received_at' => now()->subDay(),
            'unit_cost' => 1000,
            'selling_price' => 1500,
            'quantity' => 10,
            'available_quantity' => 10,
            'source' => 'purchase',
        ]);

        $ownUsage = Sale::create([
            'invoice_number' => 'MUS.TEST.0001',
            'transaction_type' => SaleTransactionType::MATERIAL_USAGE,
            'customer_id' => null,
            'created_by' => $formulator->id,
            'sale_date' => now(),
            'usage_date' => now(),
            'status' => SaleStatus::COMPLETED,
            'payment_method' => \App\Enums\PaymentMethod::TRANSFER,
            'purpose' => 'Own usage',
            'issued_by' => $formulator->id,
            'subtotal' => 1000,
            'global_discount' => 0,
            'total_discount' => 0,
            'total' => 1000,
            'cash_received' => 0,
            'change' => 0,
        ]);
        $ownItem = SaleItem::create([
            'sale_id' => $ownUsage->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'cost_price' => 1000,
            'total_cost' => 1000,
            'unit_price' => 1000,
            'discount' => 0,
            'final_price' => 1000,
            'subtotal' => 1000,
        ]);
        SaleItemBatch::create([
            'sale_item_id' => $ownItem->id,
            'batch_id' => $batch->id,
            'quantity' => 1,
            'unit_cost' => 1000,
        ]);

        $otherUsage = Sale::create([
            'invoice_number' => 'MUS.TEST.0002',
            'transaction_type' => SaleTransactionType::MATERIAL_USAGE,
            'customer_id' => null,
            'created_by' => $otherUser->id,
            'sale_date' => now(),
            'usage_date' => now(),
            'status' => SaleStatus::COMPLETED,
            'payment_method' => \App\Enums\PaymentMethod::TRANSFER,
            'purpose' => 'Other usage',
            'issued_by' => $otherUser->id,
            'subtotal' => 1000,
            'global_discount' => 0,
            'total_discount' => 0,
            'total' => 1000,
            'cash_received' => 0,
            'change' => 0,
        ]);
        $otherItem = SaleItem::create([
            'sale_id' => $otherUsage->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'cost_price' => 1000,
            'total_cost' => 1000,
            'unit_price' => 1000,
            'discount' => 0,
            'final_price' => 1000,
            'subtotal' => 1000,
        ]);
        SaleItemBatch::create([
            'sale_item_id' => $otherItem->id,
            'batch_id' => $batch->id,
            'quantity' => 1,
            'unit_cost' => 1000,
        ]);

        $response = $this->actingAs($formulator)->get(route('material-usages.index'));

        $response->assertOk();
        $response->assertSee('MUS.TEST.0001');
        $response->assertDontSee('MUS.TEST.0002');
    }
}
