<?php

namespace Tests\Feature;

use App\Enums\PurchaseStatus;
use App\Enums\UserRole;
use App\Models\Batch;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\User;
use App\Support\TransactionContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BatchTransactionResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_batch_transaction_resolver_routes_material_receipts_and_legacy_purchases_with_specific_labels(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        $receipt = $this->createPurchaseBatch($user, $product, TransactionContext::MATERIAL_RECEIPT, 'MR.260626.0301', 'MR-BATCH-0301');
        $purchase = $this->createPurchaseBatch($user, $product, TransactionContext::LEGACY_PURCHASE, 'PO.260626.0301', 'PO-BATCH-0301');

        $receiptLink = TransactionContext::resolveBatchTransactionLink($receipt, $user);
        $purchaseLink = TransactionContext::resolveBatchTransactionLink($purchase, $user);

        $this->assertSame('Material Receipt', $receipt->source_label);
        $this->assertSame('material-receipts.show', $receiptLink['route']);
        $this->assertSame($receipt->purchase_id, $receiptLink['parameters']['purchase']);

        $this->assertSame('Legacy Purchase', $purchase->source_label);
        $this->assertSame('purchases.show', $purchaseLink['route']);
        $this->assertSame($purchase->purchase_id, $purchaseLink['parameters']['purchase']);
    }

    public function test_batch_transaction_resolver_fails_safe_for_opening_stock_legacy_sync_missing_and_unauthorized_cases(): void
    {
        $admin = User::factory()->create();
        $formulator = User::factory()->create(['role' => UserRole::FORMULATOR]);
        $product = Product::factory()->create();

        $openingBatch = Batch::create([
            'product_id' => $product->id,
            'batch_number' => 'OS-BATCH-0401',
            'expiry_date' => now()->addMonths(5)->toDateString(),
            'received_at' => now()->subDay(),
            'storage_location' => 'Opening Rack',
            'unit_cost' => 5000,
            'selling_price' => 7000,
            'quantity' => 5,
            'available_quantity' => 5,
            'source' => 'opening_balance',
        ]);

        $legacySyncBatch = Batch::create([
            'product_id' => $product->id,
            'batch_number' => 'LEGACY-BATCH-0401',
            'expiry_date' => null,
            'received_at' => now()->subDay(),
            'storage_location' => 'Legacy Rack',
            'unit_cost' => 0,
            'selling_price' => 0,
            'quantity' => 5,
            'available_quantity' => 5,
            'source' => 'legacy_sync',
        ]);

        $purchaseBatch = $this->createPurchaseBatch($admin, $product, TransactionContext::LEGACY_PURCHASE, 'PO.260626.0401', 'PO-BATCH-0401');

        $openingLink = TransactionContext::resolveBatchTransactionLink($openingBatch, $admin);
        $legacySyncLink = TransactionContext::resolveBatchTransactionLink($legacySyncBatch, $admin);

        $this->assertSame('reports.inventory-movement-history', $openingLink['route']);
        $this->assertSame('opening_balance', $openingLink['parameters']['transaction_type']);
        $this->assertSame('reports.inventory-movement-history', $legacySyncLink['route']);
        $this->assertSame('legacy_sync', $legacySyncLink['parameters']['transaction_type']);
        $this->assertNull(TransactionContext::resolveBatchTransactionLink(Batch::create([
            'product_id' => $product->id,
            'batch_number' => 'UNSUPPORTED-0401',
            'expiry_date' => null,
            'received_at' => now(),
            'storage_location' => 'Unknown Rack',
            'unit_cost' => 0,
            'selling_price' => 0,
            'quantity' => 1,
            'available_quantity' => 1,
            'source' => 'purchase',
        ]), $admin));
        $this->assertNull(TransactionContext::resolveBatchTransactionLink($purchaseBatch, $formulator));
    }

    private function createPurchaseBatch(User $user, Product $product, string $context, string $transactionCode, string $batchNumber): Batch
    {
        $purchase = Purchase::create([
            'supplier_id' => null,
            'invoice_number' => $batchNumber,
            'transaction_code' => $transactionCode,
            'purchase_date' => now(),
            'status' => PurchaseStatus::RECEIVED,
            'created_by' => $user->id,
            'entry_context' => $context,
            'total' => 10000,
        ]);

        $purchaseItem = PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'batch_number' => $batchNumber,
            'expiry_date' => now()->addMonths(6)->toDateString(),
            'storage_location' => 'Resolver Rack',
            'quantity' => 2,
            'unit_price' => 5000,
            'selling_price' => 7000,
            'subtotal' => 10000,
        ]);

        return Batch::create([
            'product_id' => $product->id,
            'purchase_id' => $purchase->id,
            'purchase_item_id' => $purchaseItem->id,
            'batch_number' => $batchNumber,
            'expiry_date' => $purchaseItem->expiry_date,
            'received_at' => now()->subDay(),
            'storage_location' => 'Resolver Rack',
            'unit_cost' => 5000,
            'selling_price' => 7000,
            'quantity' => 2,
            'available_quantity' => 2,
            'source' => 'purchase',
        ])->fresh();
    }
}
