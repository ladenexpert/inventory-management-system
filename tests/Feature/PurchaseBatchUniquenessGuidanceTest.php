<?php

namespace Tests\Feature;

use App\DTOs\PurchaseData;
use App\DTOs\PurchaseItemData;
use App\Models\Batch;
use App\Enums\PurchaseStatus;
use App\Exceptions\PurchaseException;
use App\Models\Product;
use App\Models\User;
use App\Services\PurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseBatchUniquenessGuidanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_batch_number_keeps_uniqueness_and_returns_guidance_message(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $service = app(PurchaseService::class);

        Batch::create([
            'product_id' => $product->id,
            'batch_number' => 'B240601',
            'expiry_date' => now()->addMonths(6)->toDateString(),
            'received_at' => now(),
            'storage_location' => 'RM-A1',
            'unit_cost' => 5000,
            'selling_price' => 7000,
            'quantity' => 5,
            'available_quantity' => 5,
            'source' => 'purchase',
        ]);

        $this->expectException(PurchaseException::class);
        $this->expectExceptionMessage("Batch No. 'B240601' is already registered in RMP");
        $this->expectExceptionMessage("must stay unique");
        $this->expectExceptionMessage("B240601-2");

        $service->createPurchase(new PurchaseData(
            supplier_id: null,
            purchase_date: now(),
            items: [
                new PurchaseItemData(
                    product_id: $product->id,
                    batch_number: 'B240601',
                    expiry_date: now()->addMonths(6)->toDateString(),
                    storage_location: 'RM-A2',
                    storage_location_id: null,
                    quantity: 3,
                    unit_price: 5200,
                    selling_price: 7300,
                ),
            ],
            invoice_number: 'PO-BATCH-002',
            entry_context: 'material_receipt',
            status: PurchaseStatus::DRAFT,
        ), $user->id);
    }
}
