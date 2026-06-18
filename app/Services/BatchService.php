<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\InventoryLog;
use App\Models\PurchaseItem;
use App\Models\SaleItemBatch;
use App\Exceptions\SaleException;
use App\Exceptions\PurchaseException;
use Illuminate\Support\Str;

class BatchService
{
    /**
     * Keep aggregate product quantity synchronized with the operational stock authority:
     * SUM(batches.available_quantity).
     */
    public function ensureBatchCoverage(Product $product): void
    {
        $batchQuantity = $this->sumAvailableQuantity($product);

        if ($batchQuantity === $product->quantity) {
            return;
        }

        if ($batchQuantity < $product->quantity) {
            $this->createManualInboundBatch(
                product: $product,
                quantity: $product->quantity - $batchQuantity,
                unitCost: (int) $product->purchase_price,
                sellingPrice: (int) $product->selling_price,
                source: 'legacy_sync',
                notes: 'Auto-generated to align existing aggregate stock with batch tracking.'
            );

            return;
        }

        $this->syncProductQuantity($product, $batchQuantity);
    }

    public function createBatchFromPurchaseItem(Purchase $purchase, PurchaseItem $item): Batch
    {
        $product = $item->product()->lockForUpdate()->firstOrFail();
        $this->ensureBatchCoverage($product);

        $productQuantityBefore = $this->sumAvailableQuantity($product);
        $productQuantityAfter = $productQuantityBefore + $item->quantity;
        $batchNumber = trim((string) ($item->batch_number ?: $this->generateBatchNumber($product, 'PO')));

        if (Batch::where('batch_number', $batchNumber)->exists()) {
            throw PurchaseException::updateFailed(
                "Batch number '{$batchNumber}' is already in use.",
                ['purchase_id' => $purchase->id, 'purchase_item_id' => $item->id]
            );
        }

        $batch = Batch::create([
            'product_id' => $product->id,
            'purchase_id' => $purchase->id,
            'purchase_item_id' => $item->id,
            'batch_number' => $batchNumber,
            'expiry_date' => $item->expiry_date,
            'received_at' => $purchase->purchase_date?->copy()->startOfDay() ?? now(),
            'unit_cost' => $item->unit_price,
            'selling_price' => $item->selling_price ?? $product->selling_price,
            'quantity' => $item->quantity,
            'available_quantity' => $item->quantity,
            'source' => 'purchase',
            'notes' => $purchase->notes,
        ]);

        $this->logInventoryMovement(
            product: $product,
            batch: $batch,
            movementType: 'purchase_receive',
            quantity: $item->quantity,
            quantityBefore: $productQuantityBefore,
            quantityAfter: $productQuantityAfter,
            purchase: $purchase,
            purchaseItem: $item,
            notes: "Batch {$batch->batch_number} received via purchase #{$purchase->id}. Batch availability: 0 -> {$item->quantity}."
        );

        $this->syncProductQuantity($product, $productQuantityAfter);

        return $batch;
    }

    public function createManualInboundBatch(
        Product $product,
        int $quantity,
        int $unitCost,
        ?int $sellingPrice,
        string $source,
        ?string $notes = null,
        ?string $batchNumber = null,
        ?string $expiryDate = null
    ): ?Batch {
        if ($quantity <= 0) {
            return null;
        }

        $this->ensureBatchCoverage($product);

        $productQuantityBefore = $this->sumAvailableQuantity($product);
        $productQuantityAfter = $productQuantityBefore + $quantity;
        $resolvedBatchNumber = trim((string) ($batchNumber ?: $this->generateBatchNumber($product, $source)));

        if (Batch::where('batch_number', $resolvedBatchNumber)->exists()) {
            throw new \InvalidArgumentException("Batch number '{$resolvedBatchNumber}' is already in use.");
        }

        $batch = Batch::create([
            'product_id' => $product->id,
            'purchase_id' => null,
            'purchase_item_id' => null,
            'batch_number' => $resolvedBatchNumber,
            'expiry_date' => $expiryDate ? \Carbon\Carbon::parse($expiryDate) : null,
            'received_at' => now(),
            'unit_cost' => $unitCost,
            'selling_price' => $sellingPrice,
            'quantity' => $quantity,
            'available_quantity' => $quantity,
            'source' => $source,
            'notes' => $notes,
        ]);

        $this->logInventoryMovement(
            product: $product,
            batch: $batch,
            movementType: $source,
            quantity: $quantity,
            quantityBefore: $productQuantityBefore,
            quantityAfter: $productQuantityAfter,
            notes: trim("Batch {$batch->batch_number} created with availability 0 -> {$quantity}. " . ($notes ?? ''))
        );

        $this->syncProductQuantity($product, $productQuantityAfter);

        return $batch;
    }

    public function adjustProductQuantity(
        Product $product,
        int $targetQuantity,
        int $unitCost,
        ?int $sellingPrice,
        ?string $notes = null
    ): void {
        $this->ensureBatchCoverage($product);

        $currentQuantity = $this->sumAvailableQuantity($product);

        if ($targetQuantity === $currentQuantity) {
            $this->syncProductQuantity($product);
            return;
        }

        if ($targetQuantity > $currentQuantity) {
            $this->createManualInboundBatch(
                product: $product,
                quantity: $targetQuantity - $currentQuantity,
                unitCost: $unitCost,
                sellingPrice: $sellingPrice,
                source: 'adjustment_in',
                notes: $notes ?: 'Stock increased from manual product adjustment.'
            );

            return;
        }

        $allocations = $this->reserveBatches($product, $currentQuantity - $targetQuantity);

        foreach ($allocations as $allocation) {
            $this->logInventoryMovement(
                product: $product,
                batch: $allocation['batch'],
                movementType: 'adjustment_out',
                quantity: -$allocation['quantity'],
                quantityBefore: $allocation['product_quantity_before'],
                quantityAfter: $allocation['product_quantity_after'],
                notes: trim(
                    "Batch {$allocation['batch']->batch_number} availability: {$allocation['batch_quantity_before']} -> {$allocation['batch_quantity_after']}. "
                    . ($notes ?: 'Stock reduced from manual product adjustment.')
                )
            );
        }

        $this->syncProductQuantity($product, $targetQuantity);
    }

    /**
     * Reserve batches for a sale item.
     * 
     * @param Product $product The product to reserve batches for
     * @param int $quantity Total quantity needed
     * @param array<array{batch_id: int, quantity: int}>|null $manualAllocations Manual batch allocations (null = auto FEFO)
     * @return array<array{
     *   batch: Batch,
     *   quantity: int,
     *   unit_cost: int,
     *   batch_quantity_before: int,
     *   batch_quantity_after: int,
     *   product_quantity_before: int,
     *   product_quantity_after: int
     * }>
     * @throws SaleException
     */
    public function reserveBatches(Product $product, int $quantity, ?array $manualAllocations = null): array
    {
        $this->ensureBatchCoverage($product);

        // If manual allocations provided, use them instead of auto FEFO
        if ($manualAllocations !== null) {
            return $this->reserveManualBatches($product, $quantity, $manualAllocations);
        }

        // Auto FEFO - existing logic
        $batches = Batch::query()
            ->where('product_id', $product->id)
            ->where('available_quantity', '>', 0)
            ->orderByRaw('CASE WHEN expiry_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('expiry_date')
            ->orderBy('received_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $available = (int) $batches->sum('available_quantity');

        if ($available < $quantity) {
            throw SaleException::insufficientStock($product->name, $quantity, $available);
        }

        $remaining = $quantity;
        $allocations = [];
        $productQuantityRunning = $available;

        foreach ($batches as $batch) {
            if ($remaining <= 0) {
                break;
            }

            $deducted = min($remaining, $batch->available_quantity);

            if ($deducted <= 0) {
                continue;
            }

            $before = $batch->available_quantity;
            $after = $before - $deducted;
            $this->assertNonNegativeQuantity($after, 'batch available quantity', [
                'batch_id' => $batch->id,
                'movement_type' => 'sale_out',
            ]);

            $productQuantityBefore = $productQuantityRunning;
            $productQuantityAfter = $productQuantityRunning - $deducted;
            $this->assertNonNegativeQuantity($productQuantityAfter, 'product quantity', [
                'product_id' => $product->id,
                'movement_type' => 'sale_out',
            ]);

            $batch->update(['available_quantity' => $after]);

            $allocations[] = [
                'batch' => $batch->fresh(),
                'quantity' => $deducted,
                'unit_cost' => (int) $batch->unit_cost,
                'batch_quantity_before' => $before,
                'batch_quantity_after' => $after,
                'product_quantity_before' => $productQuantityBefore,
                'product_quantity_after' => $productQuantityAfter,
            ];

            $remaining -= $deducted;
            $productQuantityRunning = $productQuantityAfter;
        }

        $this->syncProductQuantity($product, $productQuantityRunning);

        return $allocations;
    }

    /**
     * Reserve batches manually selected by user.
     * 
     * @param Product $product The product
     * @param int $quantity Total quantity (for validation)
     * @param array<array{batch_id: int, quantity: int}> $manualAllocations User-selected batches
     * @return array<array{
     *   batch: Batch,
     *   quantity: int,
     *   unit_cost: int,
     *   batch_quantity_before: int,
     *   batch_quantity_after: int,
     *   product_quantity_before: int,
     *   product_quantity_after: int
     * }>
     * @throws SaleException
     */
    protected function reserveManualBatches(Product $product, int $quantity, array $manualAllocations): array
    {
        $totalAllocated = array_sum(array_column($manualAllocations, 'quantity'));
        
        if ($totalAllocated !== $quantity) {
            throw SaleException::invalidDiscount(
                "Total batch allocation ({$totalAllocated}) must equal item quantity ({$quantity}) for product '{$product->name}'."
            );
        }

        $batchIds = array_column($manualAllocations, 'batch_id');
        $batches = Batch::query()
            ->where('product_id', $product->id)
            ->whereIn('id', $batchIds)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        // Validate all batches exist and belong to this product
        $missingBatches = array_diff($batchIds, $batches->keys()->toArray());
        if (!empty($missingBatches)) {
            throw SaleException::productNotFound(implode(', ', $missingBatches));
        }

        $allocations = [];
        $productQuantityRunning = $this->sumAvailableQuantity($product);

        foreach ($manualAllocations as $allocation) {
            $batchId = $allocation['batch_id'];
            $requestedQty = $allocation['quantity'];

            if ($requestedQty <= 0) {
                continue;
            }

            $batch = $batches->get($batchId);
            
            if (!$batch) {
                throw SaleException::productNotFound($batchId);
            }

            $this->assertBatchCanBeManuallyAllocated($batch, $product);

            if ($batch->available_quantity < $requestedQty) {
                throw SaleException::insufficientStock(
                    $product->name,
                    $requestedQty,
                    $batch->available_quantity
                );
            }

            $before = $batch->available_quantity;
            $after = $before - $requestedQty;
            $this->assertNonNegativeQuantity($after, 'batch available quantity', [
                'batch_id' => $batch->id,
                'movement_type' => 'sale_out_manual',
            ]);

            $productQuantityBefore = $productQuantityRunning;
            $productQuantityAfter = $productQuantityRunning - $requestedQty;
            $this->assertNonNegativeQuantity($productQuantityAfter, 'product quantity', [
                'product_id' => $product->id,
                'movement_type' => 'sale_out_manual',
            ]);

            $batch->update(['available_quantity' => $after]);

            $allocations[] = [
                'batch' => $batch->fresh(),
                'quantity' => $requestedQty,
                'unit_cost' => (int) $batch->unit_cost,
                'batch_quantity_before' => $before,
                'batch_quantity_after' => $after,
                'product_quantity_before' => $productQuantityBefore,
                'product_quantity_after' => $productQuantityAfter,
            ];

            $productQuantityRunning = $productQuantityAfter;
        }

        $this->syncProductQuantity($product, $productQuantityRunning);

        return $allocations;
    }

    public function recordSaleItemAllocations(Sale $sale, SaleItem $saleItem, array $allocations): void
    {
        if (empty($allocations)) {
            return;
        }

        SaleItemBatch::insert(array_map(function (array $allocation) use ($saleItem) {
            return [
                'sale_item_id' => $saleItem->id,
                'batch_id' => $allocation['batch']->id,
                'quantity' => $allocation['quantity'],
                'unit_cost' => $allocation['unit_cost'],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }, $allocations));

        foreach ($allocations as $allocation) {
            $this->logInventoryMovement(
                product: $saleItem->product,
                batch: $allocation['batch'],
                movementType: 'sale_out',
                quantity: -$allocation['quantity'],
                quantityBefore: $allocation['product_quantity_before'],
                quantityAfter: $allocation['product_quantity_after'],
                sale: $sale,
                saleItem: $saleItem,
                notes: "Batch {$allocation['batch']->batch_number} consumed by sale #{$sale->invoice_number}. Batch availability: {$allocation['batch_quantity_before']} -> {$allocation['batch_quantity_after']}."
            );
        }
    }

    public function restoreSaleItemBatches(Sale $sale, SaleItem $saleItem): bool
    {
        $allocations = $saleItem->saleItemBatches()->with('batch')->get();

        if ($allocations->isEmpty()) {
            return false;
        }

        $productQuantityRunning = $this->sumAvailableQuantity($saleItem->product);

        foreach ($allocations as $allocation) {
            $batch = $allocation->batch()->lockForUpdate()->first();

            if (!$batch) {
                continue;
            }

            $before = $batch->available_quantity;
            $after = $before + $allocation->quantity;
            $productQuantityBefore = $productQuantityRunning;
            $productQuantityAfter = $productQuantityRunning + $allocation->quantity;
            $this->assertNonNegativeQuantity($productQuantityAfter, 'product quantity', [
                'product_id' => $saleItem->product_id,
                'movement_type' => 'sale_cancel_restore',
            ]);

            $batch->update(['available_quantity' => $after]);

            $this->logInventoryMovement(
                product: $saleItem->product,
                batch: $batch->fresh(),
                movementType: 'sale_cancel_restore',
                quantity: $allocation->quantity,
                quantityBefore: $productQuantityBefore,
                quantityAfter: $productQuantityAfter,
                sale: $sale,
                saleItem: $saleItem,
                notes: "Stock restored after cancelling sale #{$sale->invoice_number}. Batch {$batch->batch_number} availability: {$before} -> {$after}."
            );

            $productQuantityRunning = $productQuantityAfter;
        }

        $this->syncProductQuantity($saleItem->product, $productQuantityRunning);

        return true;
    }

    public function reapplySaleItemBatches(Sale $sale, SaleItem $saleItem): bool
    {
        $allocations = $saleItem->saleItemBatches()->with('batch')->get();

        if ($allocations->isEmpty()) {
            return false;
        }

        $productQuantityRunning = $this->sumAvailableQuantity($saleItem->product);

        foreach ($allocations as $allocation) {
            $batch = $allocation->batch()->lockForUpdate()->first();

            if (!$batch) {
                throw SaleException::productNotFound($saleItem->product_id);
            }

            if ($batch->available_quantity < $allocation->quantity) {
                throw SaleException::insufficientStock(
                    $saleItem->product->name,
                    $allocation->quantity,
                    $batch->available_quantity
                );
            }

            $before = $batch->available_quantity;
            $after = $before - $allocation->quantity;
            $this->assertNonNegativeQuantity($after, 'batch available quantity', [
                'batch_id' => $batch->id,
                'movement_type' => 'sale_restore_out',
            ]);

            $productQuantityBefore = $productQuantityRunning;
            $productQuantityAfter = $productQuantityRunning - $allocation->quantity;
            $this->assertNonNegativeQuantity($productQuantityAfter, 'product quantity', [
                'product_id' => $saleItem->product_id,
                'movement_type' => 'sale_restore_out',
            ]);

            $batch->update(['available_quantity' => $after]);

            $this->logInventoryMovement(
                product: $saleItem->product,
                batch: $batch->fresh(),
                movementType: 'sale_restore_out',
                quantity: -$allocation->quantity,
                quantityBefore: $productQuantityBefore,
                quantityAfter: $productQuantityAfter,
                sale: $sale,
                saleItem: $saleItem,
                notes: "Stock re-reserved while restoring sale #{$sale->invoice_number}. Batch {$batch->batch_number} availability: {$before} -> {$after}."
            );

            $productQuantityRunning = $productQuantityAfter;
        }

        $this->syncProductQuantity($saleItem->product, $productQuantityRunning);

        return true;
    }

    public function restoreSaleItemWithoutRecordedAllocations(Sale $sale, SaleItem $saleItem): Batch
    {
        $product = $saleItem->product()->lockForUpdate()->firstOrFail();

        return $this->createManualInboundBatch(
            product: $product,
            quantity: $saleItem->quantity,
            unitCost: (int) ($saleItem->cost_price ?: $product->purchase_price),
            sellingPrice: (int) ($saleItem->unit_price ?: $product->selling_price),
            source: 'sale_cancel_restore',
            notes: "Restored stock for sale #{$sale->invoice_number} without original batch allocation history."
        );
    }

    public function syncProductQuantity(Product $product, ?int $expectedQuantity = null): int
    {
        $summary = $product->batches()
            ->selectRaw('COALESCE(SUM(available_quantity), 0) as quantity')
            ->selectRaw('COALESCE(SUM(available_quantity * unit_cost), 0) as inventory_cost_value')
            ->first();

        $quantity = (int) ($summary->quantity ?? 0);
        $inventoryCostValue = (int) ($summary->inventory_cost_value ?? 0);
        $this->assertNonNegativeQuantity($quantity, 'product quantity', ['product_id' => $product->id]);

        if ($expectedQuantity !== null && $quantity !== $expectedQuantity) {
            throw new \RuntimeException(
                "Product quantity sync mismatch for product #{$product->id}. Expected {$expectedQuantity}, calculated {$quantity}."
            );
        }

        $updateData = ['quantity' => $quantity];

        // Keep product purchase_price aligned with current on-hand batch valuation (AVCO style).
        if ($quantity > 0) {
            $updateData['purchase_price'] = (int) round($inventoryCostValue / $quantity);
        }

        $product->update($updateData);

        return $quantity;
    }

    public function sumAvailableQuantity(Product $product): int
    {
        $quantity = (int) $product->batches()->sum('available_quantity');
        $this->assertNonNegativeQuantity($quantity, 'product quantity', ['product_id' => $product->id]);

        return $quantity;
    }

    protected function logInventoryMovement(
        Product $product,
        ?Batch $batch,
        string $movementType,
        int $quantity,
        int $quantityBefore,
        int $quantityAfter,
        ?Purchase $purchase = null,
        ?PurchaseItem $purchaseItem = null,
        ?Sale $sale = null,
        ?SaleItem $saleItem = null,
        ?string $notes = null
    ): InventoryLog {
        // Ensure at least one reference exists to prevent orphaned logs
        if (!$batch && !$purchase && !$sale) {
            throw new \Exception("Inventory log must have at least one reference (batch, purchase, or sale).");
        }

        $this->assertNonNegativeQuantity($quantityBefore, 'inventory log quantity_before', [
            'product_id' => $product->id,
            'movement_type' => $movementType,
        ]);
        $this->assertNonNegativeQuantity($quantityAfter, 'inventory log quantity_after', [
            'product_id' => $product->id,
            'movement_type' => $movementType,
        ]);

        return InventoryLog::create([
            'product_id' => $product->id,
            'batch_id' => $batch?->id,
            'purchase_id' => $purchase?->id,
            'purchase_item_id' => $purchaseItem?->id,
            'sale_id' => $sale?->id,
            'sale_item_id' => $saleItem?->id,
            'movement_type' => $movementType,
            'quantity' => $quantity,
            'quantity_before' => $quantityBefore,
            'quantity_after' => $quantityAfter,
            'notes' => $notes,
        ]);
    }

    protected function assertBatchCanBeManuallyAllocated(Batch $batch, Product $product): void
    {
        if ($batch->expiry_date && $batch->expiry_date->lt(now()->startOfDay())) {
            throw SaleException::expiredBatchSelection($batch->batch_number, $product->name);
        }
    }

    protected function assertNonNegativeQuantity(int $quantity, string $label, array $context = []): void
    {
        if ($quantity < 0) {
            $details = empty($context) ? '' : ' Context: ' . json_encode($context);
            throw new \RuntimeException("{$label} cannot be negative. Received {$quantity}.{$details}");
        }
    }

    protected function generateBatchNumber(Product $product, string $prefix): string
    {
        $cleanPrefix = Str::upper(Str::substr(preg_replace('/[^A-Za-z0-9]+/', '', $prefix) ?: 'BATCH', 0, 12));
        $productKey = Str::upper(Str::substr(preg_replace('/[^A-Za-z0-9]+/', '', $product->sku ?: ('P' . $product->id)), 0, 18));

        do {
            $number = "{$cleanPrefix}-{$productKey}-" . now()->format('ymdHis') . '-' . Str::upper(Str::random(4));
        } while (Batch::where('batch_number', $number)->exists());

        return $number;
    }
}
