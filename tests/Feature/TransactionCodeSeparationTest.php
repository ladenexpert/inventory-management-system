<?php

namespace Tests\Feature;

use App\Enums\SaleTransactionType;
use App\Enums\PaymentMethod;
use App\Enums\PurchaseStatus;
use App\Enums\SaleStatus;
use App\Models\Batch;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionCodeSeparationTest extends TestCase
{
    use RefreshDatabase;

    public function test_material_receipts_keep_reference_optional_while_generating_unique_transaction_numbers(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        $payload = fn (string $batchNumber) => [
            'context' => 'material_receipt',
            'purchase_date' => now()->toDateString(),
            'items' => [[
                'product_id' => $product->id,
                'batch_number' => $batchNumber,
                'expiry_date' => now()->addMonths(6)->toDateString(),
                'quantity' => 2,
                'unit_price' => 4000,
            ]],
        ];

        $this->actingAs($user)->post(route('purchases.store'), $payload('MR-TXN-001'))->assertRedirect();
        $this->actingAs($user)->post(route('purchases.store'), $payload('MR-TXN-002'))->assertRedirect();

        $receipts = Purchase::query()
            ->where('entry_context', 'material_receipt')
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $receipts);
        $this->assertTrue($receipts->pluck('transaction_code')->every(fn (?string $code) => is_string($code) && str_starts_with($code, 'MR.')));
        $this->assertSame(2, $receipts->pluck('transaction_code')->unique()->count());
        $this->assertTrue($receipts->pluck('invoice_number')->every(fn ($reference) => $reference === null));
    }

    public function test_material_receipts_allow_duplicate_reference_while_transaction_numbers_remain_unique(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();

        $payload = fn (string $batchNumber) => [
            'context' => 'material_receipt',
            'purchase_date' => now()->toDateString(),
            'invoice_number' => 'DN-EXT-001',
            'items' => [[
                'product_id' => $product->id,
                'batch_number' => $batchNumber,
                'expiry_date' => now()->addMonths(6)->toDateString(),
                'quantity' => 2,
                'unit_price' => 4000,
            ]],
        ];

        $this->actingAs($user)->post(route('purchases.store'), $payload('MR-REF-001'))->assertRedirect();
        $this->actingAs($user)->post(route('purchases.store'), $payload('MR-REF-002'))->assertRedirect();

        $receipts = Purchase::query()
            ->where('entry_context', 'material_receipt')
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $receipts);
        $this->assertSame(['DN-EXT-001', 'DN-EXT-001'], $receipts->pluck('invoice_number')->all());
        $this->assertSame(2, $receipts->pluck('transaction_code')->unique()->count());
    }

    public function test_material_usage_generates_transaction_numbers_without_forcing_reference(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $product = Product::factory()->create(['quantity' => 10, 'purchase_price' => 5000]);

        Batch::create([
            'product_id' => $product->id,
            'batch_number' => 'MU-TXN-001',
            'expiry_date' => now()->addMonth()->toDateString(),
            'received_at' => now()->subDay(),
            'unit_cost' => 5000,
            'selling_price' => 7000,
            'quantity' => 10,
            'available_quantity' => 10,
            'source' => 'purchase',
        ]);

        $response = $this->actingAs($user)->postJson(route('material-usages.store'), [
            'usage_date' => now()->toDateString(),
            'purpose' => 'Pilot issue',
            'team_id' => $team->id,
            'requested_by' => 'QA',
            'issued_by' => $user->id,
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 2,
                'unit_price' => 0,
                'discount' => 0,
            ]],
        ]);

        $response->assertCreated();

        $sale = Sale::query()->latest('id')->firstOrFail();

        $this->assertSame(SaleTransactionType::MATERIAL_USAGE, $sale->transaction_type);
        $this->assertTrue(is_string($sale->transaction_code) && str_starts_with($sale->transaction_code, 'MU.'));
        $this->assertNull($sale->invoice_number);
    }

    public function test_material_usage_reference_can_repeat_without_affecting_transaction_number_uniqueness(): void
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();
        $product = Product::factory()->create(['quantity' => 12, 'purchase_price' => 5000]);

        Batch::create([
            'product_id' => $product->id,
            'batch_number' => 'MU-REF-001',
            'expiry_date' => now()->addMonth()->toDateString(),
            'received_at' => now()->subDay(),
            'unit_cost' => 5000,
            'selling_price' => 7000,
            'quantity' => 12,
            'available_quantity' => 12,
            'source' => 'purchase',
        ]);

        $payload = [
            'usage_date' => now()->toDateString(),
            'invoice_number' => 'EXT-REQ-001',
            'purpose' => 'Pilot issue',
            'team_id' => $team->id,
            'requested_by' => 'QA',
            'issued_by' => $user->id,
            'items' => [[
                'product_id' => $product->id,
                'quantity' => 2,
                'unit_price' => 0,
                'discount' => 0,
            ]],
        ];

        $this->actingAs($user)->postJson(route('material-usages.store'), $payload)->assertCreated();
        $this->actingAs($user)->postJson(route('material-usages.store'), $payload)->assertCreated();

        $usages = Sale::query()
            ->where('transaction_type', SaleTransactionType::MATERIAL_USAGE)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $usages);
        $this->assertSame(['EXT-REQ-001', 'EXT-REQ-001'], $usages->pluck('invoice_number')->all());
        $this->assertSame(2, $usages->pluck('transaction_code')->unique()->count());
    }

    public function test_transaction_number_accessors_stay_separate_from_reference_number(): void
    {
        $user = User::factory()->create();

        $purchase = Purchase::create([
            'entry_context' => 'material_receipt',
            'transaction_code' => 'MR.260626.0001',
            'invoice_number' => 'DN-EXT-001',
            'purchase_date' => now(),
            'status' => PurchaseStatus::DRAFT,
            'created_by' => $user->id,
            'total' => 0,
        ]);

        $sale = Sale::create([
            'transaction_type' => SaleTransactionType::MATERIAL_USAGE,
            'transaction_code' => 'MU.260626.0001',
            'invoice_number' => 'REQ-EXT-001',
            'created_by' => $user->id,
            'issued_by' => $user->id,
            'sale_date' => now(),
            'usage_date' => now(),
            'status' => SaleStatus::PENDING,
            'subtotal' => 0,
            'global_discount' => 0,
            'total_discount' => 0,
            'total' => 0,
            'cash_received' => 0,
            'change' => 0,
            'payment_method' => PaymentMethod::TRANSFER,
            'purpose' => 'Accessor separation check',
        ]);

        $this->assertSame('MR.260626.0001', $purchase->transaction_number);
        $this->assertSame('DN-EXT-001', $purchase->reference_number);
        $this->assertSame('MU.260626.0001', $sale->transaction_number);
        $this->assertSame('REQ-EXT-001', $sale->reference_number);
    }

    public function test_historical_rows_without_transaction_codes_keep_reference_data_without_reusing_it_as_transaction_number(): void
    {
        $user = User::factory()->create();

        $purchase = Purchase::create([
            'entry_context' => 'material_receipt',
            'transaction_code' => null,
            'invoice_number' => 'LEG-REF-001',
            'purchase_date' => now(),
            'status' => PurchaseStatus::DRAFT,
            'created_by' => $user->id,
            'total' => 0,
        ]);

        $sale = Sale::create([
            'transaction_type' => SaleTransactionType::MATERIAL_USAGE,
            'transaction_code' => null,
            'invoice_number' => 'LEG-REQ-001',
            'created_by' => $user->id,
            'issued_by' => $user->id,
            'sale_date' => now(),
            'usage_date' => now(),
            'status' => SaleStatus::PENDING,
            'subtotal' => 0,
            'global_discount' => 0,
            'total_discount' => 0,
            'total' => 0,
            'cash_received' => 0,
            'change' => 0,
            'payment_method' => PaymentMethod::TRANSFER,
            'purpose' => 'Historical accessor fallback check',
        ]);

        $this->assertNull($purchase->transaction_number);
        $this->assertSame('LEG-REF-001', $purchase->reference_number);
        $this->assertSame('MR-' . $purchase->id, $purchase->display_transaction_number);

        $this->assertNull($sale->transaction_number);
        $this->assertSame('LEG-REQ-001', $sale->reference_number);
        $this->assertSame('MU-' . $sale->id, $sale->display_transaction_number);
    }
}
