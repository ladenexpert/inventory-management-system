<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\PurchaseStatus;
use App\Enums\SaleStatus;
use App\Enums\SaleTransactionType;
use App\Models\Batch;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleItemBatch;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionViewRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_material_receipt_pages_render_successfully(): void
    {
        $user = User::factory()->create();
        $receipt = $this->createPurchaseFixture($user, true, PurchaseStatus::RECEIVED);

        $this->actingAs($user)
            ->get(route('material-receipts.index'))
            ->assertOk()
            ->assertSee('Material Receipt');

        $this->actingAs($user)
            ->get(route('material-receipts.create'))
            ->assertOk()
            ->assertSee('Create Material Receipt');

        $this->actingAs($user)
            ->get(route('material-receipts.show', $receipt))
            ->assertOk()
            ->assertSee('Material Receipt Details')
            ->assertSee('Receipt Reference')
            ->assertDontSee('Mark as Paid');
    }

    public function test_legacy_purchase_pages_render_successfully(): void
    {
        $user = User::factory()->create();
        $purchase = $this->createPurchaseFixture($user, false, PurchaseStatus::RECEIVED);

        $this->actingAs($user)
            ->get(route('purchases.index'))
            ->assertOk()
            ->assertSee('Legacy Purchases');

        $this->actingAs($user)
            ->get(route('purchases.create'))
            ->assertOk()
            ->assertSee('Create Legacy Purchase');

        $this->actingAs($user)
            ->get(route('purchases.show', $purchase))
            ->assertOk()
            ->assertSee('Legacy Purchase Details')
            ->assertSee('Mark as Paid');

        $this->actingAs($user)
            ->get(route('purchases.print', $purchase))
            ->assertOk()
            ->assertSee('Legacy Purchase Receipt');
    }

    public function test_material_usage_pages_render_successfully(): void
    {
        $user = User::factory()->create();
        $usage = $this->createSaleFixture($user, SaleTransactionType::MATERIAL_USAGE, 1);

        $this->actingAs($user)
            ->get(route('material-usages.index'))
            ->assertOk()
            ->assertSee('Material Usage');

        $this->actingAs($user)
            ->get(route('material-usages.create'))
            ->assertOk()
            ->assertSee('Create Material Usage');

        $this->actingAs($user)
            ->get(route('material-usages.show', $usage))
            ->assertOk()
            ->assertSee('Material Usage Details')
            ->assertSee('Total Issued Cost');
    }

    public function test_legacy_sales_pages_render_successfully_with_multiple_items(): void
    {
        $user = User::factory()->create();
        $sale = $this->createSaleFixture($user, SaleTransactionType::SALE, 2);

        $this->actingAs($user)
            ->get(route('sales.index'))
            ->assertOk()
            ->assertSee('Legacy Sales');

        $this->actingAs($user)
            ->get(route('sales.create'))
            ->assertOk();

        $this->actingAs($user)
            ->get(route('sales.show', $sale))
            ->assertOk()
            ->assertSee('Legacy Sale Details')
            ->assertSee('Fixture Product 1')
            ->assertSee('Fixture Product 2');

        $this->actingAs($user)
            ->get(route('sales.print', $sale))
            ->assertOk()
            ->assertSee('Legacy Sales Invoice')
            ->assertSee('Fixture Product 1')
            ->assertSee('Fixture Product 2');
    }

    private function createPurchaseFixture(User $user, bool $materialReceipt, PurchaseStatus $status): Purchase
    {
        $supplier = $materialReceipt ? null : Supplier::factory()->create(['name' => 'Fixture Supplier']);
        $product = Product::factory()->create(['name' => 'Fixture RM']);

        $purchase = Purchase::create([
            'supplier_id' => $supplier?->id,
            'invoice_number' => $materialReceipt ? 'MR-FIX-001' : 'PO-FIX-001',
            'purchase_date' => now(),
            'total' => 24000,
            'status' => $status,
            'created_by' => $user->id,
            'entry_context' => $materialReceipt ? 'material_receipt' : 'legacy_purchase',
        ]);

        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'batch_number' => $materialReceipt ? 'MR-BATCH-001' : 'PO-BATCH-001',
            'expiry_date' => now()->addMonths(3)->toDateString(),
            'storage_location' => 'Fixture Rack',
            'quantity' => 3,
            'unit_price' => 8000,
            'selling_price' => 10000,
            'subtotal' => 24000,
        ]);

        return $purchase;
    }

    private function createSaleFixture(User $user, SaleTransactionType $type, int $lineCount): Sale
    {
        $sale = Sale::create([
            'invoice_number' => $type === SaleTransactionType::MATERIAL_USAGE ? 'MUS-FIX-001' : 'INV-FIX-001',
            'transaction_type' => $type,
            'customer_id' => null,
            'created_by' => $user->id,
            'issued_by' => $user->id,
            'sale_date' => now(),
            'usage_date' => now(),
            'status' => SaleStatus::COMPLETED,
            'payment_method' => PaymentMethod::TRANSFER,
            'purpose' => $type === SaleTransactionType::MATERIAL_USAGE ? 'Fixture usage' : null,
            'formula' => $type === SaleTransactionType::MATERIAL_USAGE ? 'F-001' : null,
            'project' => $type === SaleTransactionType::MATERIAL_USAGE ? 'Pilot' : null,
            'requested_by' => $type === SaleTransactionType::MATERIAL_USAGE ? 'QA Team' : null,
            'notes' => 'Fixture note',
            'cash_received' => 0,
            'change' => 0,
            'subtotal' => 0,
            'global_discount' => 0,
            'total_discount' => 0,
            'total' => 0,
        ]);

        $subtotal = 0;
        $totalCost = 0;

        for ($i = 1; $i <= $lineCount; $i++) {
            $product = Product::factory()->create([
                'name' => "Fixture Product {$i}",
                'quantity' => 10,
                'purchase_price' => 7000 + $i,
                'selling_price' => 12000 + $i,
            ]);

            $batch = Batch::create([
                'product_id' => $product->id,
                'batch_number' => "FIX-BATCH-{$i}",
                'expiry_date' => now()->addMonths(6)->toDateString(),
                'received_at' => now()->subDay(),
                'unit_cost' => 7000 + $i,
                'selling_price' => 12000 + $i,
                'quantity' => 10,
                'available_quantity' => 8,
                'source' => 'purchase',
            ]);

            $quantity = 2;
            $unitPrice = $type === SaleTransactionType::MATERIAL_USAGE ? 7000 + $i : 12000 + $i;
            $lineSubtotal = $quantity * $unitPrice;
            $lineCost = $quantity * (7000 + $i);

            $saleItem = SaleItem::create([
                'sale_id' => $sale->id,
                'product_id' => $product->id,
                'quantity' => $quantity,
                'cost_price' => 7000 + $i,
                'total_cost' => $lineCost,
                'unit_price' => $unitPrice,
                'discount' => 0,
                'final_price' => $unitPrice,
                'subtotal' => $lineSubtotal,
            ]);

            SaleItemBatch::create([
                'sale_item_id' => $saleItem->id,
                'batch_id' => $batch->id,
                'quantity' => $quantity,
                'unit_cost' => 7000 + $i,
            ]);

            $subtotal += $lineSubtotal;
            $totalCost += $lineCost;
        }

        $sale->update([
            'subtotal' => $type === SaleTransactionType::MATERIAL_USAGE ? $totalCost : $subtotal,
            'total' => $type === SaleTransactionType::MATERIAL_USAGE ? $totalCost : $subtotal,
        ]);

        return $sale->fresh();
    }
}
