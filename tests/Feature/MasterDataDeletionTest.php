<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ProductService;
use App\Services\SupplierService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterDataDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_delete_soft_deletes_and_writes_audit_log(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'quantity' => 5,
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
