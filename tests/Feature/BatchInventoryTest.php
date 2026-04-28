<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\DTOs\SaleData;
use App\Models\Unit;
use App\Models\User;
use App\Models\Batch;
use App\Models\Product;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Supplier;
use App\DTOs\ProductData;
use App\DTOs\PurchaseData;
use App\Enums\SaleStatus;
use App\Services\SaleService;
use App\Enums\PaymentMethod;
use App\Services\ProductService;
use App\Services\PurchaseService;
use App\Enums\PurchaseStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BatchInventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_batch_records_when_purchase_is_received(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create([
            'quantity' => 0,
            'purchase_price' => 10000,
            'selling_price' => 15000,
        ]);

        $purchase = app(PurchaseService::class)->createPurchase(
            PurchaseData::fromArray([
                'supplier_id' => $supplier->id,
                'invoice_number' => 'PO-001',
                'purchase_date' => '2026-04-24',
                'proof_image' => 'proofs/sample.jpg',
                'status' => PurchaseStatus::DRAFT->value,
                'items' => [
                    [
                        'product_id' => $product->id,
                        'batch_number' => 'BATCH-PO-001',
                        'expiry_date' => '2026-12-31',
                        'quantity' => 10,
                        'unit_price' => 12000,
                        'selling_price' => 17000,
                    ],
                ],
            ]),
            $user->id
        );

        app(PurchaseService::class)->markAsReceived($purchase->fresh());

        $this->assertDatabaseHas('batches', [
            'product_id' => $product->id,
            'purchase_id' => $purchase->id,
            'batch_number' => 'BATCH-PO-001',
            'quantity' => 10,
            'available_quantity' => 10,
            'source' => 'purchase',
        ]);

        $this->assertDatabaseHas('inventory_logs', [
            'product_id' => $product->id,
            'purchase_id' => $purchase->id,
            'movement_type' => 'purchase_receive',
            'quantity' => 10,
        ]);

        $product->refresh();

        $this->assertSame(10, $product->quantity);
        $this->assertSame(12000, $product->purchase_price);
        $this->assertSame(17000, $product->selling_price);
    }

    public function test_it_consumes_batches_by_nearest_expiry_and_restores_them_on_cancel(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create();
        $customer = Customer::factory()->create();
        $product = Product::factory()->create([
            'quantity' => 0,
            'purchase_price' => 10000,
            'selling_price' => 16000,
        ]);

        $purchase = app(PurchaseService::class)->createPurchase(
            PurchaseData::fromArray([
                'supplier_id' => $supplier->id,
                'invoice_number' => 'PO-002',
                'purchase_date' => '2026-04-24',
                'proof_image' => 'proofs/sample.jpg',
                'status' => PurchaseStatus::DRAFT->value,
                'items' => [
                    [
                        'product_id' => $product->id,
                        'batch_number' => 'EARLY-EXP',
                        'expiry_date' => '2026-05-01',
                        'quantity' => 5,
                        'unit_price' => 9000,
                        'selling_price' => 16000,
                    ],
                    [
                        'product_id' => $product->id,
                        'batch_number' => 'LATE-EXP',
                        'expiry_date' => '2026-08-01',
                        'quantity' => 5,
                        'unit_price' => 10000,
                        'selling_price' => 16000,
                    ],
                ],
            ]),
            $user->id
        );

        app(PurchaseService::class)->markAsReceived($purchase->fresh());

        $sale = app(SaleService::class)->createSale(
            SaleData::fromArray([
                'sale_date' => '2026-04-24',
                'payment_method' => PaymentMethod::CASH->value,
                'created_by' => $user->id,
                'customer_id' => $customer->id,
                'status' => SaleStatus::PENDING->value,
                'cash_received' => 0,
                'global_discount' => 0,
                'items' => [
                    [
                        'product_id' => $product->id,
                        'quantity' => 6,
                        'unit_price' => 16000,
                        'discount' => 0,
                    ],
                ],
            ])
        );

        $sale->load('items.saleItemBatches.batch');
        $saleItem = $sale->items->first();

        $this->assertCount(2, $saleItem->saleItemBatches);
        $this->assertSame(
            ['EARLY-EXP', 'LATE-EXP'],
            $saleItem->saleItemBatches->pluck('batch.batch_number')->all()
        );
        $this->assertSame([5, 1], $saleItem->saleItemBatches->pluck('quantity')->all());

        $product->refresh();
        $this->assertSame(4, $product->quantity);
        $this->assertSame(0, Batch::where('batch_number', 'EARLY-EXP')->value('available_quantity'));
        $this->assertSame(4, Batch::where('batch_number', 'LATE-EXP')->value('available_quantity'));

        $cancelledSale = app(SaleService::class)->cancelSale($sale->fresh());

        $this->assertSame(SaleStatus::CANCELLED, $cancelledSale->status);
        $this->assertSame(5, Batch::where('batch_number', 'EARLY-EXP')->value('available_quantity'));
        $this->assertSame(5, Batch::where('batch_number', 'LATE-EXP')->value('available_quantity'));

        $product->refresh();
        $this->assertSame(10, $product->quantity);

        $restoredSale = app(SaleService::class)->restoreSale($sale->fresh());

        $this->assertSame(SaleStatus::PENDING, $restoredSale->status);
        $this->assertSame(0, Batch::where('batch_number', 'EARLY-EXP')->value('available_quantity'));
        $this->assertSame(4, Batch::where('batch_number', 'LATE-EXP')->value('available_quantity'));

        $product->refresh();
        $this->assertSame(4, $product->quantity);
    }

    public function test_it_syncs_opening_balance_and_manual_quantity_adjustments_to_batches(): void
    {
        $category = Category::factory()->create();
        $unit = Unit::factory()->create();
        $service = app(ProductService::class);

        $product = $service->createProduct(ProductData::fromArray([
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'name' => 'Batch Ready Product',
            'item_code_ierp' => 'IERP-OB-001',
            'purchase_price' => 11000,
            'selling_price' => 15000,
            'quantity' => 8,
            'opening_batch_number' => 'OB-BATCH-001',
            'min_stock' => 2,
            'is_active' => true,
            'description' => null,
            'notes' => null,
        ]));

        $this->assertSame(8, $product->fresh()->quantity);
        $this->assertDatabaseHas('batches', [
            'product_id' => $product->id,
            'batch_number' => 'OB-BATCH-001',
            'source' => 'opening_balance',
            'available_quantity' => 8,
        ]);
        $this->assertSame('IERP-OB-001', $product->fresh()->item_code_ierp);

        $service->updateProduct($product->fresh(), ProductData::fromArray([
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'sku' => $product->sku,
            'name' => 'Batch Ready Product',
            'item_code_ierp' => 'IERP-OB-001',
            'purchase_price' => 11000,
            'selling_price' => 15000,
            'quantity' => 5,
            'min_stock' => 2,
            'is_active' => true,
            'description' => null,
            'notes' => null,
        ]));

        $this->assertSame(5, $product->fresh()->quantity);
        $this->assertDatabaseHas('inventory_logs', [
            'product_id' => $product->id,
            'movement_type' => 'adjustment_out',
            'quantity' => -3,
        ]);

        $service->updateProduct($product->fresh(), ProductData::fromArray([
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'sku' => $product->sku,
            'name' => 'Batch Ready Product',
            'item_code_ierp' => 'IERP-OB-001',
            'purchase_price' => 11500,
            'selling_price' => 15500,
            'quantity' => 9,
            'min_stock' => 2,
            'is_active' => true,
            'description' => null,
            'notes' => null,
        ]));

        $this->assertSame(9, $product->fresh()->quantity);
        $this->assertDatabaseHas('batches', [
            'product_id' => $product->id,
            'source' => 'adjustment_in',
            'available_quantity' => 4,
        ]);
    }
}
