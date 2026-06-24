<?php

namespace Tests\Feature;

use App\Enums\PurchaseStatus;
use App\Models\Batch;
use App\Models\FinanceTransaction;
use App\Models\InventoryLog;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PurchaseReceiptWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_material_receipt_process_allows_blank_invoice_and_proof(): void
    {
        $user = User::factory()->create();
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
            'batch_number' => 'RNI-REC-001',
            'expiry_date' => now()->addMonths(4)->toDateString(),
            'storage_location' => 'RM-A1 - Raw Material Rack A1',
            'quantity' => 5,
            'unit_price' => 4000,
            'selling_price' => 7000,
            'subtotal' => 20000,
        ]);

        $response = $this->actingAs($user)
            ->from(route('material-receipts.show', $purchase))
            ->patch(route('purchases.mark-received', $purchase));

        $response->assertRedirect(route('material-receipts.show', $purchase));
        $this->assertDatabaseHas('purchases', [
            'id' => $purchase->id,
            'status' => PurchaseStatus::RECEIVED->value,
        ]);
        $this->assertDatabaseHas('batches', [
            'purchase_id' => $purchase->id,
            'batch_number' => 'RNI-REC-001',
        ]);
        $this->assertSame(1, InventoryLog::where('movement_type', 'purchase_receive')->count());
    }

    public function test_legacy_purchase_process_still_requires_invoice_and_proof(): void
    {
        $user = User::factory()->create();
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
            'entry_context' => 'legacy_purchase',
        ]);

        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'batch_number' => 'LEG-REC-001',
            'expiry_date' => now()->addMonths(4)->toDateString(),
            'storage_location' => 'Rack B',
            'quantity' => 5,
            'unit_price' => 4000,
            'selling_price' => 7000,
            'subtotal' => 20000,
        ]);

        $response = $this->actingAs($user)
            ->from(route('purchases.show', $purchase))
            ->patch(route('purchases.mark-received', $purchase));

        $response->assertRedirect(route('purchases.show', $purchase));
        $response->assertSessionHasErrors(['invoice_number', 'proof_image']);
        $this->assertSame(PurchaseStatus::ORDERED, $purchase->fresh()->status);
        $this->assertSame(0, Batch::count());
    }

    public function test_legacy_purchase_process_receives_with_invoice_and_proof(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
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
            'entry_context' => 'legacy_purchase',
        ]);

        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'batch_number' => 'LEG-REC-002',
            'expiry_date' => now()->addMonths(4)->toDateString(),
            'storage_location' => 'Rack C',
            'quantity' => 5,
            'unit_price' => 4000,
            'selling_price' => 7000,
            'subtotal' => 20000,
        ]);

        $response = $this->actingAs($user)
            ->from(route('purchases.show', $purchase))
            ->patch(route('purchases.mark-received', $purchase), [
                'invoice_number' => 'PO-REG-001',
                'proof_image' => UploadedFile::fake()->image('proof.jpg'),
            ]);

        $response->assertRedirect(route('purchases.show', $purchase));
        $this->assertDatabaseHas('purchases', [
            'id' => $purchase->id,
            'invoice_number' => 'PO-REG-001',
            'status' => PurchaseStatus::RECEIVED->value,
        ]);
        $this->assertDatabaseHas('batches', [
            'purchase_id' => $purchase->id,
            'batch_number' => 'LEG-REC-002',
        ]);
    }

    public function test_material_receipt_cannot_be_marked_paid_or_post_finance(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'quantity' => 0,
            'purchase_price' => 4000,
            'selling_price' => 7000,
        ]);

        $purchase = Purchase::create([
            'supplier_id' => null,
            'purchase_date' => now(),
            'total' => 20000,
            'status' => PurchaseStatus::RECEIVED,
            'created_by' => $user->id,
            'entry_context' => 'material_receipt',
        ]);

        PurchaseItem::create([
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'batch_number' => 'RNI-REC-PAID-001',
            'expiry_date' => now()->addMonths(4)->toDateString(),
            'storage_location' => 'RM-Z1 - Pilot Rack',
            'quantity' => 5,
            'unit_price' => 4000,
            'selling_price' => 7000,
            'subtotal' => 20000,
        ]);

        $response = $this->actingAs($user)
            ->from(route('material-receipts.show', $purchase))
            ->patch(route('purchases.mark-paid', $purchase));

        $response->assertRedirect(route('material-receipts.show', $purchase));
        $response->assertSessionHas('error');
        $this->assertSame(PurchaseStatus::RECEIVED, $purchase->fresh()->status);
        $this->assertSame(0, FinanceTransaction::count());
    }
}
