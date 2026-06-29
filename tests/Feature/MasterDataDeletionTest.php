<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;
use App\Livewire\Products\ProductTable;
use App\Models\Batch;
use App\Models\InventoryAdjustment;
use App\Models\InventoryLog;
use App\Services\ProductService;
use App\Services\SupplierService;
use App\Exceptions\ProductException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MasterDataDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_delete_soft_deletes_and_writes_audit_log(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'quantity' => 0,
        ]);

        $this->actingAs($user);
        app(ProductService::class)->deleteProduct($product);

        $this->assertSoftDeleted('products', ['id' => $product->id]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'deleted',
            'auditable_type' => Product::class,
            'auditable_id' => $product->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_product_delete_is_blocked_when_active_stock_exists(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'quantity' => 5,
        ]);

        Batch::create([
            'product_id' => $product->id,
            'batch_number' => 'DEL-GUARD-001',
            'received_at' => now(),
            'unit_cost' => 5000,
            'selling_price' => 7000,
            'quantity' => 5,
            'available_quantity' => 5,
            'source' => 'purchase',
        ]);

        $this->actingAs($user);

        $this->expectException(ProductException::class);
        $this->expectExceptionMessage('Material cannot be deleted because active stock still exists.');

        app(ProductService::class)->deleteProduct($product);
    }

    public function test_product_delete_is_blocked_when_active_stock_exists_even_if_inventory_value_is_zero(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'quantity' => 4,
            'purchase_price' => 0,
            'selling_price' => 0,
        ]);

        Batch::create([
            'product_id' => $product->id,
            'batch_number' => 'DEL-GUARD-ZERO-001',
            'received_at' => now(),
            'unit_cost' => 0,
            'selling_price' => 0,
            'quantity' => 4,
            'available_quantity' => 4,
            'source' => 'purchase',
        ]);

        $this->actingAs($user);

        $this->expectException(ProductException::class);
        $this->expectExceptionMessage('Material cannot be deleted because active stock still exists.');

        app(ProductService::class)->deleteProduct($product);
    }

    public function test_zero_stock_product_delete_does_not_create_inventory_or_adjustment_records(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'quantity' => 0,
        ]);

        $existingLog = InventoryLog::create([
            'product_id' => $product->id,
            'batch_id' => null,
            'movement_type' => 'legacy_sync',
            'quantity' => 0,
            'quantity_before' => 0,
            'quantity_after' => 0,
            'notes' => 'Existing historical movement',
        ]);

        $this->actingAs($user);
        app(ProductService::class)->deleteProduct($product);

        $this->assertSoftDeleted('products', ['id' => $product->id]);
        $this->assertSame(1, InventoryLog::count());
        $this->assertDatabaseHas('inventory_logs', ['id' => $existingLog->id]);
        $this->assertSame(0, InventoryAdjustment::count());
    }

    public function test_product_table_ui_delete_action_cannot_delete_product_with_active_batch_stock(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'quantity' => 3,
        ]);

        Batch::create([
            'product_id' => $product->id,
            'batch_number' => 'UI-DEL-BLOCK-001',
            'received_at' => now(),
            'unit_cost' => 4000,
            'selling_price' => 6000,
            'quantity' => 3,
            'available_quantity' => 3,
            'source' => 'purchase',
        ]);

        $this->actingAs($user);

        Livewire::test(ProductTable::class)
            ->call('delete', $product->id)
            ->assertDispatched('toast');

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'deleted_at' => null,
        ]);
        $this->assertSame(0, InventoryAdjustment::count());
        $this->assertSame(0, InventoryLog::count());
    }

    public function test_product_table_ui_delete_action_cannot_delete_product_when_quantity_cache_still_shows_stock(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'quantity' => 2,
            'purchase_price' => 0,
            'selling_price' => 0,
        ]);

        $this->actingAs($user);

        Livewire::test(ProductTable::class)
            ->call('delete', $product->id)
            ->assertDispatched('toast');

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'deleted_at' => null,
        ]);
        $this->assertSame(0, InventoryAdjustment::count());
        $this->assertSame(0, InventoryLog::count());
    }

    public function test_product_table_ui_delete_action_allows_zero_stock_product_soft_delete(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'quantity' => 0,
        ]);

        $this->actingAs($user);

        Livewire::test(ProductTable::class)
            ->call('delete', $product->id)
            ->assertDispatched('toast');

        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }

    public function test_product_table_ui_bulk_delete_uses_centralized_guard(): void
    {
        $user = User::factory()->create();
        $blockedProduct = Product::factory()->create([
            'quantity' => 5,
        ]);
        $allowedProduct = Product::factory()->create([
            'quantity' => 0,
        ]);

        Batch::create([
            'product_id' => $blockedProduct->id,
            'batch_number' => 'UI-BULK-BLOCK-001',
            'received_at' => now(),
            'unit_cost' => 3000,
            'selling_price' => 4500,
            'quantity' => 5,
            'available_quantity' => 5,
            'source' => 'purchase',
        ]);

        $this->actingAs($user);

        Livewire::test(ProductTable::class)
            ->set('checkboxValues', [$blockedProduct->id, $allowedProduct->id])
            ->call('bulkDelete')
            ->assertDispatched('toast');

        $this->assertDatabaseHas('products', [
            'id' => $blockedProduct->id,
            'deleted_at' => null,
        ]);
        $this->assertSoftDeleted('products', ['id' => $allowedProduct->id]);
    }

    public function test_supplier_delete_keeps_historical_purchase_relation_available(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create();
        $purchase = Purchase::create([
            'supplier_id' => $supplier->id,
            'purchase_date' => now(),
            'total' => 0,
            'status' => 'draft',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user);
        app(SupplierService::class)->deleteSupplier($supplier);

        $this->assertSoftDeleted('suppliers', ['id' => $supplier->id]);
        $this->assertSame($supplier->id, $purchase->fresh()->supplier?->id);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Supplier::class,
            'auditable_id' => $supplier->id,
        ]);
    }
}
