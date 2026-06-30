<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\StorageLocation;
use App\Services\BatchService;
use Illuminate\Database\Seeder;

class DemoMaterialStockSeeder extends Seeder
{
    public function run(): void
    {
        $products = Product::query()
            ->orderBy('id')
            ->get();

        if ($products->isEmpty()) {
            return;
        }

        $locations = StorageLocation::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        /** @var BatchService $batchService */
        $batchService = app(BatchService::class);

        $batchService->withinStockMutationScope(function () use ($products, $locations, $batchService) {
            foreach ($products as $index => $product) {
                if ($product->batches()->exists() || (int) $product->quantity > 0) {
                    continue;
                }

                $location = $locations->isEmpty()
                    ? null
                    : $locations[$index % $locations->count()];

                $quantity = 10 + (($index * 7) % 91);

                $batchService->createManualInboundBatch(
                    product: $product,
                    quantity: $quantity,
                    unitCost: (int) $product->purchase_price,
                    sellingPrice: (int) $product->selling_price,
                    source: 'opening_balance',
                    notes: 'Explicit demo stock seeded for development/testing only.',
                    batchNumber: sprintf('DEMO-OPEN-%03d', $index + 1),
                    expiryDate: now()->addDays(180 + $index)->toDateString(),
                    storageLocation: $location?->display_label,
                    storageLocationId: $location?->id,
                );
            }
        });
    }
}
