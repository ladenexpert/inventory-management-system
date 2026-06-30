<?php

namespace App\Services;

use Exception;
use App\Models\Batch;
use App\Models\PhysicalForm;
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
        protected InventoryAdjustmentService $inventoryAdjustmentService,
        protected DashboardCacheService $dashboardCache,
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

                $physicalFormCode = $this->resolvePhysicalFormCode($data);
                $physicalFormId = $this->resolvePhysicalFormId($data, $physicalFormCode);

                $product = Product::create([
                    'category_id' => $data->category_id,
                    'unit_id' => $data->unit_id,
                    'supplier_id' => $data->supplier_id,
                    'sku' => $sku,
                    'item_code_ierp' => $data->item_code_ierp,
                    'name' => $data->name,
                    'physical_form' => $physicalFormCode,
                    'physical_form_id' => $physicalFormId,
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
                    $this->batchService->withinStockMutationScope(function () use ($product, $data, $openingLocation) {
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
                    });
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
                $currentQuantity = $this->batchService->sumAvailableQuantity($lockedProduct);

                $physicalFormCode = $this->resolvePhysicalFormCode($data);
                $physicalFormId = $this->resolvePhysicalFormId($data, $physicalFormCode);

                $lockedProduct->update([
                    'category_id' => $data->category_id,
                    'unit_id' => $data->unit_id,
                    'supplier_id' => $data->supplier_id,
                    'sku' => $data->sku ?? $lockedProduct->sku,
                    'item_code_ierp' => $data->item_code_ierp,
                    'name' => $data->name,
                    'physical_form' => $physicalFormCode,
                    'physical_form_id' => $physicalFormId,
                    'purchase_price' => $data->purchase_price,
                    'selling_price' => $data->selling_price,
                    'min_stock' => $data->min_stock,
                    'is_active' => $data->is_active,
                    'description' => $data->description,
                    'notes' => $data->notes,
                ]);

                $adjustment = null;

                if ($targetQuantity !== $currentQuantity) {
                    $direction = $targetQuantity > $currentQuantity ? 'in' : 'out';
                    $adjustment = $this->inventoryAdjustmentService->create([
                        'adjustment_type' => 'manual_stock_adjustment',
                        'direction' => $direction,
                        'source' => 'product_form',
                        'reference' => $lockedProduct->item_code_ierp ?: $lockedProduct->sku,
                        'notes' => 'Stock adjusted from product form update.',
                        'adjusted_by' => auth()->id(),
                        'adjusted_at' => now(),
                        'meta' => [
                            'product_id' => $lockedProduct->id,
                            'quantity_before' => $currentQuantity,
                            'quantity_after' => $targetQuantity,
                        ],
                    ], 'ADJ');
                }

                $this->batchService->withinStockMutationScope(function () use ($lockedProduct, $targetQuantity, $data, $adjustment) {
                    $this->batchService->adjustProductQuantity(
                        product: $lockedProduct,
                        targetQuantity: $targetQuantity,
                        unitCost: $data->purchase_price,
                        sellingPrice: $data->selling_price,
                        notes: 'Stock adjusted from product form update.',
                        inventoryAdjustment: $adjustment ?? null,
                    );
                });

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
                $lockedProduct = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
                $this->assertCanDeleteProduct($lockedProduct);

                $lockedProduct->delete();
                $this->auditLogService->logDeletion($lockedProduct);
                $this->dashboardCache->forgetDashboardData();

            } catch (Exception $e) {
                if ($e instanceof ProductException) {
                    throw $e;
                }

                throw ProductException::deletionFailed($e->getMessage(), ['id' => $product->id]);
            }
        });
    }

    protected function assertCanDeleteProduct(Product $product): void
    {
        $batchActiveStock = (int) Batch::query()
            ->where('product_id', $product->id)
            ->lockForUpdate()
            ->selectRaw('COALESCE(SUM(available_quantity), 0) as total_quantity')
            ->value('total_quantity');

        $cachedQuantity = (int) $product->quantity;

        if ($batchActiveStock > 0 || $cachedQuantity > 0) {
            $message = $batchActiveStock > 0
                ? 'Material cannot be deleted because active stock still exists. Reduce the stock to zero through Stock Take, Adjustment, or Usage before deleting this material.'
                : 'Material cannot be deleted because the material stock summary still shows quantity on hand. Review the stock balance first, then try deleting this material again.';

            throw ProductException::deletionFailed(
                $message,
                [
                    'id' => $product->id,
                    'batch_active_stock' => $batchActiveStock,
                    'cached_quantity' => $cachedQuantity,
                ],
            );
        }
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

    private function resolvePhysicalFormCode(ProductData $data): ?string
    {
        if ($data->physical_form !== null) {
            return $data->physical_form;
        }

        if ($data->physical_form_id === null) {
            return null;
        }

        return PhysicalForm::withTrashed()->find($data->physical_form_id)?->code;
    }

    private function resolvePhysicalFormId(ProductData $data, ?string $physicalFormCode): ?int
    {
        if ($data->physical_form_id !== null) {
            return $data->physical_form_id;
        }

        if ($physicalFormCode === null) {
            return null;
        }

        return PhysicalForm::withTrashed()
            ->where('code', $physicalFormCode)
            ->value('id');
    }
}
