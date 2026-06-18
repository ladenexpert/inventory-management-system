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
                        'expiry_date' => now()->addMonths(6)->toDateString(),
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
            'quantity_before' => 0,
            'quantity_after' => 10,
        ]);

        $product->refresh();

        $this->assertSame(10, $product->quantity);
        $this->assertSame(10, (int) Batch::where('product_id', $product->id)->sum('available_quantity'));
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
                        'expiry_date' => now()->addDays(5)->toDateString(),
                        'quantity' => 5,
                        'unit_price' => 9000,
                        'selling_price' => 16000,
                    ],
                    [
                        'product_id' => $product->id,
                        'batch_number' => 'LATE-EXP',
                        'expiry_date' => now()->addDays(90)->toDateString(),
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
        $this->assertSame(4, (int) Batch::where('product_id', $product->id)->sum('available_quantity'));

        $cancelledSale = app(SaleService::class)->cancelSale($sale->fresh());

        $this->assertSame(SaleStatus::CANCELLED, $cancelledSale->status);
        $this->assertSame(5, Batch::where('batch_number', 'EARLY-EXP')->value('available_quantity'));
        $this->assertSame(5, Batch::where('batch_number', 'LATE-EXP')->value('available_quantity'));

        $product->refresh();
        $this->assertSame(10, $product->quantity);
        $this->assertSame(10, (int) Batch::where('product_id', $product->id)->sum('available_quantity'));

        $restoredSale = app(SaleService::class)->restoreSale($sale->fresh());

        $this->assertSame(SaleStatus::PENDING, $restoredSale->status);
        $this->assertSame(0, Batch::where('batch_number', 'EARLY-EXP')->value('available_quantity'));
        $this->assertSame(4, Batch::where('batch_number', 'LATE-EXP')->value('available_quantity'));

        $product->refresh();
        $this->assertSame(4, $product->quantity);
        $this->assertSame(4, (int) Batch::where('product_id', $product->id)->sum('available_quantity'));
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
        $this->assertSame(9, (int) Batch::where('product_id', $product->id)->sum('available_quantity'));
    }

    public function test_it_recalculates_purchase_price_from_active_batch_valuation(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create([
            'quantity' => 0,
            'purchase_price' => 9000,
            'selling_price' => 15000,
        ]);

        $purchaseOne = app(PurchaseService::class)->createPurchase(
            PurchaseData::fromArray([
                'supplier_id' => $supplier->id,
                'invoice_number' => 'PO-AVCO-001',
                'purchase_date' => '2026-04-25',
                'proof_image' => 'proofs/sample.jpg',
                'status' => PurchaseStatus::DRAFT->value,
                'items' => [
                    [
                        'product_id' => $product->id,
                        'batch_number' => 'AVCO-1',
                        'expiry_date' => now()->addMonths(6)->toDateString(),
                        'quantity' => 10,
                        'unit_price' => 10000,
                        'selling_price' => 15000,
                    ],
                ],
            ]),
            $user->id
        );
        app(PurchaseService::class)->markAsReceived($purchaseOne->fresh());

        $purchaseTwo = app(PurchaseService::class)->createPurchase(
            PurchaseData::fromArray([
                'supplier_id' => $supplier->id,
                'invoice_number' => 'PO-AVCO-002',
                'purchase_date' => '2026-04-26',
                'proof_image' => 'proofs/sample.jpg',
                'status' => PurchaseStatus::DRAFT->value,
                'items' => [
                    [
                        'product_id' => $product->id,
                        'batch_number' => 'AVCO-2',
                        'expiry_date' => now()->addMonths(9)->toDateString(),
                        'quantity' => 5,
                        'unit_price' => 16000,
                        'selling_price' => 17000,
                    ],
                ],
            ]),
            $user->id
        );
        app(PurchaseService::class)->markAsReceived($purchaseTwo->fresh());

        $product->refresh();
        $this->assertSame(15, $product->quantity);
        $this->assertSame(12000, $product->purchase_price); // (10*10000 + 5*16000) / 15

        $sale = app(SaleService::class)->createSale(
            SaleData::fromArray([
                'sale_date' => '2026-04-27',
                'payment_method' => PaymentMethod::CASH->value,
                'created_by' => $user->id,
                'status' => SaleStatus::PENDING->value,
                'cash_received' => 0,
                'items' => [
                    [
                        'product_id' => $product->id,
                        'quantity' => 10,
                        'unit_price' => 17000,
                        'discount' => 0,
                    ],
                ],
            ])
        );

        $this->assertNotNull($sale->id);
        $product->refresh();
        $this->assertSame(5, $product->quantity);
        $this->assertSame(5, (int) Batch::where('product_id', $product->id)->sum('available_quantity'));
        $this->assertSame(16000, $product->purchase_price);
    }

    public function test_it_uses_sale_line_unit_price_as_transaction_selling_price_snapshot(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create();
        $customer = Customer::factory()->create();
        $product = Product::factory()->create([
            'quantity' => 0,
            'purchase_price' => 10000,
            'selling_price' => 20000,
        ]);

        $purchase = app(PurchaseService::class)->createPurchase(
            PurchaseData::fromArray([
                'supplier_id' => $supplier->id,
                'invoice_number' => 'PO-SALE-PRICE-001',
                'purchase_date' => '2026-04-28',
                'proof_image' => 'proofs/sample.jpg',
                'status' => PurchaseStatus::DRAFT->value,
                'items' => [
                    [
                        'product_id' => $product->id,
                        'batch_number' => 'SALE-PRICE-BATCH',
                        'expiry_date' => null,
                        'quantity' => 3,
                        'unit_price' => 10000,
                        'selling_price' => 20000,
                    ],
                ],
            ]),
            $user->id
        );
        app(PurchaseService::class)->markAsReceived($purchase->fresh());

        $sale = app(SaleService::class)->createSale(
            SaleData::fromArray([
                'sale_date' => '2026-04-28',
                'payment_method' => PaymentMethod::CASH->value,
                'created_by' => $user->id,
                'customer_id' => $customer->id,
                'status' => SaleStatus::PENDING->value,
                'cash_received' => 0,
                'items' => [
                    [
                        'product_id' => $product->id,
                        'quantity' => 1,
                        'unit_price' => 18500,
                        'discount' => 0,
                    ],
                ],
            ])
        );

        $saleItem = $sale->items()->first();
        $this->assertNotNull($saleItem);
        $this->assertSame(18500, $saleItem->unit_price);
        $this->assertSame(18500, $saleItem->final_price);
        $this->assertSame(18500, $saleItem->subtotal);
    }

    public function test_it_deducts_the_manually_selected_batch_only(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create([
            'quantity' => 0,
            'purchase_price' => 10000,
            'selling_price' => 17000,
        ]);

        $purchase = app(PurchaseService::class)->createPurchase(
            PurchaseData::fromArray([
                'supplier_id' => $supplier->id,
                'invoice_number' => 'PO-MANUAL-001',
                'purchase_date' => now()->toDateString(),
                'proof_image' => 'proofs/sample.jpg',
                'status' => PurchaseStatus::DRAFT->value,
                'items' => [
                    [
                        'product_id' => $product->id,
                        'batch_number' => 'MANUAL-A',
                        'expiry_date' => now()->addDays(10)->toDateString(),
                        'quantity' => 5,
                        'unit_price' => 10000,
                        'selling_price' => 17000,
                    ],
                    [
                        'product_id' => $product->id,
                        'batch_number' => 'MANUAL-B',
                        'expiry_date' => now()->addDays(40)->toDateString(),
                        'quantity' => 5,
                        'unit_price' => 11000,
                        'selling_price' => 17000,
                    ],
                ],
            ]),
            $user->id
        );
        app(PurchaseService::class)->markAsReceived($purchase->fresh());

        $manualBatch = Batch::where('batch_number', 'MANUAL-B')->firstOrFail();

        $sale = app(SaleService::class)->createSale(
            SaleData::fromArray([
                'sale_date' => now()->toDateString(),
                'payment_method' => PaymentMethod::CASH->value,
                'created_by' => $user->id,
                'status' => SaleStatus::PENDING->value,
                'cash_received' => 0,
                'items' => [
                    [
                        'product_id' => $product->id,
                        'quantity' => 3,
                        'unit_price' => 17000,
                        'discount' => 0,
                        'batch_allocations' => [
                            ['batch_id' => $manualBatch->id, 'quantity' => 3],
                        ],
                    ],
                ],
            ])
        );

        $saleItem = $sale->items()->with('saleItemBatches.batch')->firstOrFail();

        $this->assertCount(1, $saleItem->saleItemBatches);
        $this->assertSame('MANUAL-B', $saleItem->saleItemBatches->first()->batch->batch_number);
        $this->assertSame(5, Batch::where('batch_number', 'MANUAL-A')->value('available_quantity'));
        $this->assertSame(2, Batch::where('batch_number', 'MANUAL-B')->value('available_quantity'));
        $this->assertSame(7, $product->fresh()->quantity);
        $this->assertSame(7, (int) Batch::where('product_id', $product->id)->sum('available_quantity'));

        $this->assertDatabaseHas('inventory_logs', [
            'product_id' => $product->id,
            'batch_id' => $manualBatch->id,
            'movement_type' => 'sale_out',
            'quantity' => -3,
            'quantity_before' => 10,
            'quantity_after' => 7,
        ]);
    }

    public function test_sale_cannot_exceed_available_stock(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create([
            'quantity' => 0,
            'purchase_price' => 10000,
            'selling_price' => 16000,
        ]);

        $purchase = app(PurchaseService::class)->createPurchase(
            PurchaseData::fromArray([
                'supplier_id' => $supplier->id,
                'invoice_number' => 'PO-STOCK-001',
                'purchase_date' => now()->toDateString(),
                'proof_image' => 'proofs/sample.jpg',
                'status' => PurchaseStatus::DRAFT->value,
                'items' => [
                    [
                        'product_id' => $product->id,
                        'batch_number' => 'STOCK-001',
                        'expiry_date' => now()->addMonth()->toDateString(),
                        'quantity' => 3,
                        'unit_price' => 10000,
                        'selling_price' => 16000,
                    ],
                ],
            ]),
            $user->id
        );
        app(PurchaseService::class)->markAsReceived($purchase->fresh());

        $this->expectExceptionMessage("Insufficient stock for product '{$product->name}'. Requested: 4, Available: 3.");

        try {
            app(SaleService::class)->createSale(
                SaleData::fromArray([
                    'sale_date' => now()->toDateString(),
                    'payment_method' => PaymentMethod::CASH->value,
                    'created_by' => $user->id,
                    'status' => SaleStatus::PENDING->value,
                    'cash_received' => 0,
                    'items' => [
                        [
                            'product_id' => $product->id,
                            'quantity' => 4,
                            'unit_price' => 16000,
                            'discount' => 0,
                        ],
                    ],
                ])
            );
        } finally {
            $product->refresh();
            $this->assertSame(3, $product->quantity);
            $this->assertSame(3, (int) Batch::where('product_id', $product->id)->sum('available_quantity'));
        }
    }

    public function test_expired_batch_cannot_be_manually_allocated(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create([
            'quantity' => 0,
            'purchase_price' => 10000,
            'selling_price' => 18000,
        ]);

        $purchase = app(PurchaseService::class)->createPurchase(
            PurchaseData::fromArray([
                'supplier_id' => $supplier->id,
                'invoice_number' => 'PO-EXP-001',
                'purchase_date' => now()->toDateString(),
                'proof_image' => 'proofs/sample.jpg',
                'status' => PurchaseStatus::DRAFT->value,
                'items' => [
                    [
                        'product_id' => $product->id,
                        'batch_number' => 'EXPIRED-MANUAL',
                        'expiry_date' => now()->subDay()->toDateString(),
                        'quantity' => 2,
                        'unit_price' => 10000,
                        'selling_price' => 18000,
                    ],
                ],
            ]),
            $user->id
        );
        app(PurchaseService::class)->markAsReceived($purchase->fresh());

        $expiredBatch = Batch::where('batch_number', 'EXPIRED-MANUAL')->firstOrFail();

        $this->expectExceptionMessage("Expired batch 'EXPIRED-MANUAL' cannot be allocated manually for product '{$product->name}'.");

        try {
            app(SaleService::class)->createSale(
                SaleData::fromArray([
                    'sale_date' => now()->toDateString(),
                    'payment_method' => PaymentMethod::CASH->value,
                    'created_by' => $user->id,
                    'status' => SaleStatus::PENDING->value,
                    'cash_received' => 0,
                    'items' => [
                        [
                            'product_id' => $product->id,
                            'quantity' => 1,
                            'unit_price' => 18000,
                            'discount' => 0,
                            'batch_allocations' => [
                                ['batch_id' => $expiredBatch->id, 'quantity' => 1],
                            ],
                        ],
                    ],
                ])
            );
        } finally {
            $product->refresh();
            $this->assertSame(2, $product->quantity);
            $this->assertSame(2, (int) Batch::where('product_id', $product->id)->sum('available_quantity'));
        }
    }

    public function test_auto_fefo_ignores_expired_batches_and_uses_next_valid_layer(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create();
        $customer = Customer::factory()->create();
        $product = Product::factory()->create([
            'quantity' => 0,
            'purchase_price' => 10000,
            'selling_price' => 18000,
        ]);

        $purchase = app(PurchaseService::class)->createPurchase(
            PurchaseData::fromArray([
                'supplier_id' => $supplier->id,
                'invoice_number' => 'PO-FEFO-EXP-001',
                'purchase_date' => now()->toDateString(),
                'proof_image' => 'proofs/sample.jpg',
                'status' => PurchaseStatus::DRAFT->value,
                'items' => [
                    [
                        'product_id' => $product->id,
                        'batch_number' => 'FEFO-EXPIRED',
                        'expiry_date' => now()->subDay()->toDateString(),
                        'quantity' => 4,
                        'unit_price' => 10000,
                        'selling_price' => 18000,
                    ],
                    [
                        'product_id' => $product->id,
                        'batch_number' => 'FEFO-VALID',
                        'expiry_date' => now()->addDays(20)->toDateString(),
                        'quantity' => 5,
                        'unit_price' => 11000,
                        'selling_price' => 18000,
                    ],
                ],
            ]),
            $user->id
        );
        app(PurchaseService::class)->markAsReceived($purchase->fresh());

        $sale = app(SaleService::class)->createSale(
            SaleData::fromArray([
                'sale_date' => now()->toDateString(),
                'payment_method' => PaymentMethod::CASH->value,
                'created_by' => $user->id,
                'customer_id' => $customer->id,
                'status' => SaleStatus::PENDING->value,
                'cash_received' => 0,
                'items' => [
                    [
                        'product_id' => $product->id,
                        'quantity' => 2,
                        'unit_price' => 18000,
                        'discount' => 0,
                    ],
                ],
            ])
        );

        $saleItem = $sale->items()->with('saleItemBatches.batch')->firstOrFail();

        $this->assertSame(['FEFO-VALID'], $saleItem->saleItemBatches->pluck('batch.batch_number')->all());
        $this->assertSame(4, Batch::where('batch_number', 'FEFO-EXPIRED')->value('available_quantity'));
        $this->assertSame(3, Batch::where('batch_number', 'FEFO-VALID')->value('available_quantity'));
        $this->assertSame(7, $product->fresh()->quantity);
    }

    public function test_manual_batch_allocation_must_match_requested_quantity(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create([
            'quantity' => 0,
            'purchase_price' => 10000,
            'selling_price' => 17000,
        ]);

        $purchase = app(PurchaseService::class)->createPurchase(
            PurchaseData::fromArray([
                'supplier_id' => $supplier->id,
                'invoice_number' => 'PO-MANUAL-MISMATCH-001',
                'purchase_date' => now()->toDateString(),
                'proof_image' => 'proofs/sample.jpg',
                'status' => PurchaseStatus::DRAFT->value,
                'items' => [
                    [
                        'product_id' => $product->id,
                        'batch_number' => 'MANUAL-MISMATCH',
                        'expiry_date' => now()->addDays(15)->toDateString(),
                        'quantity' => 5,
                        'unit_price' => 10000,
                        'selling_price' => 17000,
                    ],
                ],
            ]),
            $user->id
        );
        app(PurchaseService::class)->markAsReceived($purchase->fresh());

        $batch = Batch::where('batch_number', 'MANUAL-MISMATCH')->firstOrFail();

        $this->expectExceptionMessage("Invalid batch allocation: Total batch allocation (2) must equal item quantity (3) for product '{$product->name}'.");

        app(SaleService::class)->createSale(
            SaleData::fromArray([
                'sale_date' => now()->toDateString(),
                'payment_method' => PaymentMethod::CASH->value,
                'created_by' => $user->id,
                'status' => SaleStatus::PENDING->value,
                'cash_received' => 0,
                'items' => [
                    [
                        'product_id' => $product->id,
                        'quantity' => 3,
                        'unit_price' => 17000,
                        'discount' => 0,
                        'batch_allocations' => [
                            ['batch_id' => $batch->id, 'quantity' => 2],
                        ],
                    ],
                ],
            ])
        );
    }

    public function test_zero_cost_batches_are_allowed_and_keep_batch_valuation_zero(): void
    {
        $user = User::factory()->create();
        $supplier = Supplier::factory()->create();
        $product = Product::factory()->create([
            'quantity' => 0,
            'purchase_price' => 5000,
            'selling_price' => 15000,
        ]);

        $purchase = app(PurchaseService::class)->createPurchase(
            PurchaseData::fromArray([
                'supplier_id' => $supplier->id,
                'invoice_number' => 'PO-ZERO-COST-001',
                'purchase_date' => now()->toDateString(),
                'proof_image' => 'proofs/sample.jpg',
                'status' => PurchaseStatus::DRAFT->value,
                'items' => [
                    [
                        'product_id' => $product->id,
                        'batch_number' => 'ZERO-COST-BATCH',
                        'expiry_date' => now()->addDays(60)->toDateString(),
                        'quantity' => 6,
                        'unit_price' => 0,
                        'selling_price' => 15000,
                    ],
                ],
            ]),
            $user->id
        );
        app(PurchaseService::class)->markAsReceived($purchase->fresh());

        $batch = Batch::where('batch_number', 'ZERO-COST-BATCH')->firstOrFail();

        $this->assertSame(0, (int) $batch->unit_cost);
        $this->assertSame(0, (int) $batch->inventory_value);
        $this->assertSame(6, $product->fresh()->quantity);
        $this->assertSame(0, $product->fresh()->purchase_price);
    }
}
