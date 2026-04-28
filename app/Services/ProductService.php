<?php

namespace App\Services;

use Exception;
use App\Models\Product;
use Illuminate\Support\Str;
use App\DTOs\ProductData;
use App\Exceptions\ProductException;
use Illuminate\Support\Facades\DB;

class ProductService
{
    public function __construct(
        protected BatchService $batchService
    ) {
    }

    /**
     * Create a new product.
     */
    public function createProduct(ProductData $data): Product
    {
        return DB::transaction(function () use ($data) {
            try {
                $sku = $data->sku ?? $this->generateUniqueSku();

                $product = Product::create([
                    'category_id' => $data->category_id,
                    'unit_id' => $data->unit_id,
                    'sku' => $sku,
                    'item_code_ierp' => $data->item_code_ierp,
                    'name' => $data->name,
                    'purchase_price' => $data->purchase_price,
                    'selling_price' => $data->selling_price,
                    'quantity' => 0,
                    'min_stock' => $data->min_stock,
                    'is_active' => $data->is_active,
                    'description' => $data->description,
                    'notes' => $data->notes,
                ]);

                if ($data->quantity > 0) {
                    $this->batchService->createManualInboundBatch(
                        product: $product,
                        quantity: $data->quantity,
                        unitCost: $data->purchase_price,
                        sellingPrice: $data->selling_price,
                        source: 'opening_balance',
                        notes: 'Opening balance created from initial product setup.',
                        batchNumber: $data->opening_batch_number
                    );
                }

                return $product->refresh();

            } catch (Exception $e) {
                throw ProductException::creationFailed($e->getMessage(), [
                    'data' => (array) $data,
                    'trace' => $e->getTraceAsString()
                ]);
            }
        });
    }

    /**
     * Update an existing product.
     */
    public function updateProduct(Product $product, ProductData $data): Product
    {
        return DB::transaction(function () use ($product, $data) {
            try {
                $lockedProduct = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
                $targetQuantity = $data->quantity;

                $lockedProduct->update([
                    'category_id' => $data->category_id,
                    'unit_id' => $data->unit_id,
                    'sku' => $data->sku ?? $lockedProduct->sku,
                    'item_code_ierp' => $data->item_code_ierp,
                    'name' => $data->name,
                    'purchase_price' => $data->purchase_price,
                    'selling_price' => $data->selling_price,
                    'min_stock' => $data->min_stock,
                    'is_active' => $data->is_active,
                    'description' => $data->description,
                    'notes' => $data->notes,
                ]);

                $this->batchService->adjustProductQuantity(
                    product: $lockedProduct,
                    targetQuantity: $targetQuantity,
                    unitCost: $data->purchase_price,
                    sellingPrice: $data->selling_price,
                    notes: 'Stock adjusted from product form update.'
                );

                return $lockedProduct->refresh();

            } catch (Exception $e) {
                throw ProductException::updateFailed($e->getMessage(), [
                    'id'   => $product->id,
                    'data' => (array) $data
                ]);
            }
        });
    }

    /**
     * Delete a product.
     */
    public function deleteProduct(Product $product): void
    {
        DB::transaction(function () use ($product) {
            try {
                if (
                    $product->purchaseItems()->exists()
                    || $product->saleItems()->exists()
                    || $product->batches()->exists()
                    || $product->inventoryLogs()->exists()
                ) {
                    throw new Exception('Cannot delete product because it already has stock movement history.');
                }

                $product->delete();

            } catch (Exception $e) {
                throw ProductException::deletionFailed($e->getMessage(), ['id' => $product->id]);
            }
        });
    }

    /**
     * Generate a unique SKU in format P.YYMMDD.XXXX.
     */
    private function generateUniqueSku(): string
    {
        $prefix = 'P.' . date('ymd') . '.';

        do {
            $sku = $prefix . strtoupper(Str::random(4));
        } while (Product::where('sku', $sku)->exists());

        return $sku;
    }
}
