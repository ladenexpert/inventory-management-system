<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
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

        $this->actingAs($user)->get(route('material-usages.create'))->assertForbidden();
        $this->actingAs($user)->get(route('users.index'))->assertForbidden();
        $this->actingAs($user)->get(route('material-receipts.index'))->assertForbidden();
        $this->actingAs($user)->get(route('finance.transactions.index'))->assertForbidden();
    }

    public function test_formulator_usage_history_supports_monitoring_all_usage_records_as_read_only(): void
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
        $response->assertSee('MUS.TEST.0002');
        $response->assertDontSee('Create Usage');
    }

    public function test_rm_desk_can_create_material_usage(): void
    {
        $rmDesk = User::factory()->create([
            'role' => UserRole::RM_DESK,
        ]);

        $product = Product::factory()->create([
            'quantity' => 8,
            'purchase_price' => 1200,
        ]);

        Batch::create([
            'product_id' => $product->id,
            'batch_number' => 'RMD-CREATE-001',
            'expiry_date' => now()->addDays(20)->toDateString(),
            'received_at' => now()->subDay(),
            'unit_cost' => 1200,
            'selling_price' => 1500,
            'quantity' => 8,
            'available_quantity' => 8,
            'source' => 'purchase',
        ]);

        $this->actingAs($rmDesk)
            ->postJson(route('material-usages.store'), [
                'usage_date' => now()->toDateString(),
                'purpose' => 'RM Desk usage',
                'issued_by' => $rmDesk->id,
                'items' => [
                    [
                        'product_id' => $product->id,
                        'quantity' => 3,
                        'discount' => 0,
                    ],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.transaction_type', SaleTransactionType::MATERIAL_USAGE->value);

        $this->assertDatabaseHas('sales', [
            'created_by' => $rmDesk->id,
            'purpose' => 'RM Desk usage',
            'transaction_type' => SaleTransactionType::MATERIAL_USAGE->value,
        ]);
    }

    public function test_rm_desk_can_cancel_only_their_own_material_usage(): void
    {
        $rmDesk = User::factory()->create([
            'role' => UserRole::RM_DESK,
        ]);
        $otherRmDesk = User::factory()->create([
            'role' => UserRole::RM_DESK,
        ]);

        $ownUsage = Sale::create([
            'invoice_number' => 'MUS.RMD.0001',
            'transaction_type' => SaleTransactionType::MATERIAL_USAGE,
            'created_by' => $rmDesk->id,
            'issued_by' => $rmDesk->id,
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
            'purpose' => 'Own usage',
        ]);

        $otherUsage = Sale::create([
            'invoice_number' => 'MUS.RMD.0002',
            'transaction_type' => SaleTransactionType::MATERIAL_USAGE,
            'created_by' => $otherRmDesk->id,
            'issued_by' => $otherRmDesk->id,
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
            'purpose' => 'Other usage',
        ]);

        $this->actingAs($rmDesk)
            ->delete(route('material-usages.destroy', $ownUsage))
            ->assertRedirect(route('material-usages.index'));

        $this->assertSame(SaleStatus::CANCELLED, $ownUsage->fresh()->status);

        $this->actingAs($rmDesk)
            ->delete(route('material-usages.destroy', $otherUsage))
            ->assertForbidden();
    }

    public function test_rm_desk_can_restore_only_their_own_material_usage(): void
    {
        $rmDesk = User::factory()->create([
            'role' => UserRole::RM_DESK,
        ]);
        $otherRmDesk = User::factory()->create([
            'role' => UserRole::RM_DESK,
        ]);

        $ownUsage = Sale::create([
            'invoice_number' => 'MUS.RMD.0003',
            'transaction_type' => SaleTransactionType::MATERIAL_USAGE,
            'created_by' => $rmDesk->id,
            'issued_by' => $rmDesk->id,
            'sale_date' => now(),
            'usage_date' => now(),
            'status' => SaleStatus::CANCELLED,
            'subtotal' => 0,
            'global_discount' => 0,
            'total_discount' => 0,
            'total' => 0,
            'cash_received' => 0,
            'change' => 0,
            'payment_method' => PaymentMethod::TRANSFER,
            'purpose' => 'Own cancelled usage',
        ]);

        $otherUsage = Sale::create([
            'invoice_number' => 'MUS.RMD.0004',
            'transaction_type' => SaleTransactionType::MATERIAL_USAGE,
            'created_by' => $otherRmDesk->id,
            'issued_by' => $otherRmDesk->id,
            'sale_date' => now(),
            'usage_date' => now(),
            'status' => SaleStatus::CANCELLED,
            'subtotal' => 0,
            'global_discount' => 0,
            'total_discount' => 0,
            'total' => 0,
            'cash_received' => 0,
            'change' => 0,
            'payment_method' => PaymentMethod::TRANSFER,
            'purpose' => 'Other cancelled usage',
        ]);

        $this->actingAs($rmDesk)
            ->patch(route('material-usages.restore', $ownUsage))
            ->assertRedirect(route('material-usages.show', $ownUsage));

        $this->assertSame(SaleStatus::PENDING, $ownUsage->fresh()->status);

        $this->actingAs($rmDesk)
            ->patch(route('material-usages.restore', $otherUsage))
            ->assertForbidden();
    }
}
