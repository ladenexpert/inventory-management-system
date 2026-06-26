<?php

namespace Tests\Feature;

use App\Enums\PurchaseStatus;
use App\Enums\SaleTransactionType;
use App\Models\Batch;
use App\Models\FinanceTransaction;
use App\Models\InventoryLog;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Sale;
use App\Models\SaleItemBatch;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StockSyncRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_material_receipt_confirm_does_not_loop_stock_sync_queries(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $product = Product::factory()->create([
            'quantity' => 0,
            'purchase_price' => 4000,
            'selling_price' => 7000,
        ]);

        $purchase = Purchase::create([
            'supplier_id' => null,
            'purchase_date' => now(),
            'total' => 20000,
            'status' => PurchaseStatus::ORDERED,
            'created_by' => $user->id,
            'entry_context' => 'material_receipt',
        ]);

        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'batch_number' => 'SYNC-RNI-001',
            'expiry_date' => now()->addMonths(4)->toDateString(),
            'storage_location' => 'RM-A1',
            'quantity' => 5,
            'unit_price' => 4000,
            'selling_price' => 7000,
            'subtotal' => 20000,
        ]);

        $aggregateQueryCount = $this->countAggregateQueriesDuring(function () use ($user, $purchase, &$response) {
            $response = $this->actingAs($user)
                ->from(route('material-receipts.show', $purchase))
                ->patch(route('purchases.mark-received', $purchase));
        });

        $response->assertRedirect(route('material-receipts.show', $purchase));
        $this->assertSame(PurchaseStatus::RECEIVED, $purchase->fresh()->status);
        $this->assertSame(5, (int) Batch::where('batch_number', 'SYNC-RNI-001')->value('available_quantity'));
        $this->assertProductMatchesBatchQuantity($product);
        $this->assertSame(1, InventoryLog::where('purchase_id', $purchase->id)->where('movement_type', 'purchase_receive')->count());
        $this->assertSame(0, FinanceTransaction::count());
        $this->assertLessThanOrEqual(5, $aggregateQueryCount);
    }

    public function test_legacy_purchase_receive_does_not_loop_stock_sync_queries(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $product = Product::factory()->create([
            'quantity' => 0,
            'purchase_price' => 4500,
            'selling_price' => 7200,
        ]);

        $purchase = Purchase::create([
            'supplier_id' => null,
            'purchase_date' => now(),
            'total' => 22500,
            'status' => PurchaseStatus::ORDERED,
            'created_by' => $user->id,
            'entry_context' => 'legacy_purchase',
        ]);

        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'batch_number' => 'SYNC-LEG-001',
            'expiry_date' => now()->addMonths(5)->toDateString(),
            'storage_location' => 'LEG-A1',
            'quantity' => 5,
            'unit_price' => 4500,
            'selling_price' => 7200,
            'subtotal' => 22500,
        ]);

        $aggregateQueryCount = $this->countAggregateQueriesDuring(function () use ($user, $purchase, &$response) {
            $response = $this->actingAs($user)
                ->from(route('purchases.show', $purchase))
                ->patch(route('purchases.mark-received', $purchase), [
                    'invoice_number' => 'PO-SYNC-001',
                    'proof_image' => UploadedFile::fake()->image('proof.jpg'),
                ]);
        });

        $response->assertRedirect(route('purchases.show', $purchase));
        $this->assertSame(PurchaseStatus::RECEIVED, $purchase->fresh()->status);
        $this->assertSame(5, (int) Batch::where('batch_number', 'SYNC-LEG-001')->value('available_quantity'));
        $this->assertProductMatchesBatchQuantity($product);
        $this->assertSame(1, InventoryLog::where('purchase_id', $purchase->id)->where('movement_type', 'purchase_receive')->count());
        $this->assertSame(0, FinanceTransaction::where('reference_type', Purchase::class)->where('reference_id', $purchase->id)->count());
        $this->assertLessThanOrEqual(5, $aggregateQueryCount);
    }

    public function test_material_usage_issue_does_not_loop_stock_sync_queries(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $product = Product::factory()->create([
            'name' => 'Citric Acid',
            'quantity' => 10,
            'purchase_price' => 12000,
        ]);

        Batch::create([
            'product_id' => $product->id,
            'batch_number' => 'SYNC-USAGE-001',
            'expiry_date' => now()->addDays(15)->toDateString(),
            'received_at' => now()->subDays(2),
            'unit_cost' => 12000,
            'selling_price' => 15000,
            'quantity' => 10,
            'available_quantity' => 10,
            'source' => 'purchase',
        ]);

        $aggregateQueryCount = $this->countAggregateQueriesDuring(function () use ($user, $product, $team, &$response) {
            $response = $this->actingAs($user)->postJson(route('material-usages.store'), [
                'usage_date' => now()->toDateString(),
                'purpose' => 'Loop regression',
                'team_id' => $team->id,
                'requested_by' => 'RNI Ops',
                'issued_by' => $user->id,
                'items' => [
                    [
                        'product_id' => $product->id,
                        'quantity' => 4,
                        'unit_price' => 12000,
                        'discount' => 0,
                    ],
                ],
            ]);
        });

        $response->assertCreated();

        $usage = Sale::query()->latest('id')->firstOrFail();

        $this->assertSame(SaleTransactionType::MATERIAL_USAGE, $usage->transaction_type);
        $this->assertSame(6, (int) Batch::where('batch_number', 'SYNC-USAGE-001')->value('available_quantity'));
        $this->assertProductMatchesBatchQuantity($product);
        $this->assertSame(1, SaleItemBatch::whereHas('saleItem', fn ($query) => $query->where('sale_id', $usage->id))->count());
        $this->assertSame(1, InventoryLog::where('sale_id', $usage->id)->where('movement_type', 'sale_out')->count());
        $this->assertSame(0, FinanceTransaction::where('reference_type', Sale::class)->where('reference_id', $usage->id)->count());
        $this->assertLessThanOrEqual(5, $aggregateQueryCount);
    }

    public function test_legacy_purchase_mark_paid_posts_finance_once(): void
    {
        $user = User::factory()->create();

        $purchase = Purchase::create([
            'supplier_id' => null,
            'invoice_number' => 'PO-PAID-001',
            'purchase_date' => now(),
            'proof_image' => 'proofs/existing-proof.jpg',
            'total' => 22500,
            'status' => PurchaseStatus::RECEIVED,
            'created_by' => $user->id,
            'entry_context' => 'legacy_purchase',
        ]);

        $firstResponse = $this->actingAs($user)
            ->from(route('purchases.show', $purchase))
            ->patch(route('purchases.mark-paid', $purchase));

        $firstResponse->assertRedirect(route('purchases.show', $purchase));
        $this->assertSame(PurchaseStatus::PAID, $purchase->fresh()->status);
        $this->assertSame(1, FinanceTransaction::where('reference_type', Purchase::class)->where('reference_id', $purchase->id)->count());

        $secondResponse = $this->actingAs($user)
            ->from(route('purchases.show', $purchase))
            ->patch(route('purchases.mark-paid', $purchase));

        $secondResponse->assertRedirect(route('purchases.show', $purchase));
        $secondResponse->assertSessionHas('error');
        $this->assertSame(1, FinanceTransaction::where('reference_type', Purchase::class)->where('reference_id', $purchase->id)->count());
    }

    public function test_legacy_sale_process_does_not_loop_stock_sync_queries(): void
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
            'batch_number' => 'SYNC-SALE-001',
            'expiry_date' => now()->addMonths(6)->toDateString(),
            'received_at' => now()->subDay(),
            'unit_cost' => 12000,
            'selling_price' => 18000,
            'quantity' => 8,
            'available_quantity' => 8,
            'source' => 'purchase',
        ]);

        $aggregateQueryCount = $this->countAggregateQueriesDuring(function () use ($user, $product, &$response) {
            $response = $this->actingAs($user)->postJson(route('sales.store'), [
                'sale_date' => now()->toDateString(),
                'payment_method' => 'transfer',
                'status' => 'completed',
                'global_discount' => 0,
                'notes' => 'Loop regression',
                'items' => [
                    [
                        'product_id' => $product->id,
                        'quantity' => 3,
                        'unit_price' => 18000,
                        'discount' => 1000,
                    ],
                ],
            ]);
        });

        $response->assertCreated();

        $sale = Sale::query()->latest('id')->firstOrFail();

        $this->assertSame(SaleTransactionType::SALE, $sale->transaction_type);
        $this->assertSame(3, (int) SaleItemBatch::whereHas('saleItem', fn ($query) => $query->where('sale_id', $sale->id))->sum('quantity'));
        $this->assertSame(5, (int) Batch::where('batch_number', 'SYNC-SALE-001')->value('available_quantity'));
        $this->assertProductMatchesBatchQuantity($product);
        $this->assertSame(1, InventoryLog::where('sale_id', $sale->id)->where('movement_type', 'sale_out')->count());
        $this->assertSame(1, FinanceTransaction::where('reference_type', Sale::class)->where('reference_id', $sale->id)->count());
        $this->assertLessThanOrEqual(5, $aggregateQueryCount);
    }

    protected function countAggregateQueriesDuring(callable $callback): int
    {
        $connection = DB::connection();

        $connection->flushQueryLog();
        $connection->enableQueryLog();

        $callback();

        $queries = $connection->getQueryLog();
        $connection->disableQueryLog();
        $connection->flushQueryLog();

        return collect($queries)
            ->filter(function (array $query): bool {
                $sql = strtolower(str_replace(['"', '`', '[', ']'], '', $query['query']));

                return str_contains($sql, 'sum(available_quantity)');
            })
            ->count();
    }

    protected function assertProductMatchesBatchQuantity(Product $product): void
    {
        $this->assertSame(
            (int) Batch::where('product_id', $product->id)->sum('available_quantity'),
            (int) $product->fresh()->quantity
        );
    }
}
