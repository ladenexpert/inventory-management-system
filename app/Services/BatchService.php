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

        $product->update(['quantity' => $batchQuantity]);
    }

    public function createBatchFromPurchaseItem(Purchase $purchase, PurchaseItem $item): Batch
    {
        $product = $item->product()->lockForUpdate()->firstOrFail();
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
            quantityBefore: 0,
            quantityAfter: $item->quantity,
            purchase: $purchase,
            purchaseItem: $item,
            notes: "Batch received via purchase #{$purchase->id}."
        );

        $this->syncProductQuantity($product);

        return $batch;
    }

    public function createManualInboundBatch(
        Product $product,
        int $quantity,
        int $unitCost,
        ?int $sellingPrice,
        string $source,
        ?string $notes = null,
        ?string $batchNumber = null
    ): ?Batch {
        if ($quantity <= 0) {
            return null;
        }

        $resolvedBatchNumber = trim((string) ($batchNumber ?: $this->generateBatchNumber($product, $source)));

        if (Batch::where('batch_number', $resolvedBatchNumber)->exists()) {
            throw new \InvalidArgumentException("Batch number '{$resolvedBatchNumber}' is already in use.");
        }

        $batch = Batch::create([
            'product_id' => $product->id,
            'purchase_id' => null,
            'purchase_item_id' => null,
            'batch_number' => $resolvedBatchNumber,
            'expiry_date' => null,
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
            quantityBefore: 0,
            quantityAfter: $quantity,
            notes: $notes
        );

        $this->syncProductQuantity($product);

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
                quantityBefore: $allocation['quantity_before'],
                quantityAfter: $allocation['quantity_after'],
                notes: $notes ?: 'Stock reduced from manual product adjustment.'
            );
        }

        $this->syncProductQuantity($product);
    }

    public function reserveBatches(Product $product, int $quantity): array
    {
        $this->ensureBatchCoverage($product);

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

            $batch->update(['available_quantity' => $after]);

            $allocations[] = [
                'batch' => $batch->fresh(),
                'quantity' => $deducted,
                'unit_cost' => (int) $batch->unit_cost,
                'quantity_before' => $before,
                'quantity_after' => $after,
            ];

            $remaining -= $deducted;
        }

        $this->syncProductQuantity($product);

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
                quantityBefore: $allocation['quantity_before'],
                quantityAfter: $allocation['quantity_after'],
                sale: $sale,
                saleItem: $saleItem,
                notes: "Batch consumed by sale #{$sale->invoice_number}."
            );
        }
    }

    public function restoreSaleItemBatches(Sale $sale, SaleItem $saleItem): bool
    {
        $allocations = $saleItem->saleItemBatches()->with('batch')->get();

        if ($allocations->isEmpty()) {
            return false;
        }

        foreach ($allocations as $allocation) {
            $batch = $allocation->batch()->lockForUpdate()->first();

            if (!$batch) {
                continue;
            }

            $before = $batch->available_quantity;
            $after = $before + $allocation->quantity;

            $batch->update(['available_quantity' => $after]);

            $this->logInventoryMovement(
                product: $saleItem->product,
                batch: $batch->fresh(),
                movementType: 'sale_cancel_restore',
                quantity: $allocation->quantity,
                quantityBefore: $before,
                quantityAfter: $after,
                sale: $sale,
                saleItem: $saleItem,
                notes: "Stock restored after cancelling sale #{$sale->invoice_number}."
            );
        }

        $this->syncProductQuantity($saleItem->product);

        return true;
    }

    public function reapplySaleItemBatches(Sale $sale, SaleItem $saleItem): bool
    {
        $allocations = $saleItem->saleItemBatches()->with('batch')->get();

        if ($allocations->isEmpty()) {
            return false;
        }

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

            $batch->update(['available_quantity' => $after]);

            $this->logInventoryMovement(
                product: $saleItem->product,
                batch: $batch->fresh(),
                movementType: 'sale_restore_out',
                quantity: -$allocation->quantity,
                quantityBefore: $before,
                quantityAfter: $after,
                sale: $sale,
                saleItem: $saleItem,
                notes: "Stock re-reserved while restoring sale #{$sale->invoice_number}."
            );
        }

        $this->syncProductQuantity($saleItem->product);

        return true;
    }

    public function syncProductQuantity(Product $product): int
    {
        $quantity = $this->sumAvailableQuantity($product);
        $product->update(['quantity' => $quantity]);

        return $quantity;
    }

    public function sumAvailableQuantity(Product $product): int
    {
        return (int) $product->batches()->sum('available_quantity');
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
