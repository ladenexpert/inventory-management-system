<?php

namespace App\Services;

use Exception;
use App\Models\Product;
use App\Models\StorageLocation;
use Illuminate\Support\Str;
use App\DTOs\ProductData;
use App\Exceptions\ProductException;
use Illuminate\Support\Facades\DB;

class ProductService
{
    public function __construct(
        protected BatchService $batchService,
        protected AuditLogService $auditLogService,
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
                    'supplier_id' => $data->supplier_id,
                    'sku' => $sku,
                    'item_code_ierp' => $data->item_code_ierp,
                    'name' => $data->name,
                    'physical_form' => $data->physical_form,
                    'purchase_price' => $data->purchase_price,
                    'selling_price' => $data->selling_price,
                    'quantity' => 0,
                    'min_stock' => $data->min_stock,
                    'is_active' => $data->is_active,
                    'description' => $data->description,
                    'notes' => $data->notes,
                ]);

                $openingLocation = $data->opening_storage_location_id
                    ? StorageLocation::withTrashed()->find($data->opening_storage_location_id)
                    : null;

                if ($data->quantity > 0) {
                    $this->batchService->createManualInboundBatch(
                        product: $product,
                        quantity: $data->quantity,
                        unitCost: $data->purchase_price,
                        sellingPrice: $data->selling_price,
                        source: 'opening_balance',
                        notes: 'Opening balance created from initial product setup.',
                        batchNumber: $data->opening_batch_number,
                        expiryDate: $data->opening_expiry_date,
                        storageLocation: $openingLocation?->display_label ?? $data->opening_storage_location,
                        storageLocationId: $data->opening_storage_location_id,
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
                    'supplier_id' => $data->supplier_id,
                    'sku' => $data->sku ?? $lockedProduct->sku,
                    'item_code_ierp' => $data->item_code_ierp,
                    'name' => $data->name,
                    'physical_form' => $data->physical_form,
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
                $product->delete();
                $this->auditLogService->logDeletion($product);

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
