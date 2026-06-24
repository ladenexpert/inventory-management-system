<?php

namespace App\Services;

use App\Enums\BatchAllocationPolicy;
use App\Exceptions\PurchaseException;
use App\Exceptions\SaleException;
use App\Models\Batch;
use App\Models\InventoryLog;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleItemBatch;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class BatchService
{
    /**
     * Stock mutations may happen several times inside one DB transaction.
     * Defer product cache sync + inventory log writes until the outer scope completes.
     *
     * @var int
     */
    protected int $deferredStockMutationDepth = 0;

    /**
     * @var array<int, array{product: Product, expected_quantity: int|null}>
     */
    protected array $pendingProductSyncs = [];

    /**
     * @var list<array<string, int|string|null>>
     */
    protected array $pendingInventoryLogs = [];

    /**
     * @var array<int, bool>
     */
    protected static array $syncingProducts = [];

    public function __construct(
        protected BatchPolicyService $batchPolicyService,
        protected FefoService $fefoService,
    ) {
    }

    public function withinStockMutationScope(callable $callback): mixed
    {
        $outermostScope = $this->deferredStockMutationDepth === 0;
        $this->deferredStockMutationDepth++;

        try {
            $result = $callback();
        } catch (\Throwable $exception) {
            $this->deferredStockMutationDepth--;

            if ($outermostScope) {
                $this->resetDeferredStockMutations();
            }

            throw $exception;
        }

        $this->deferredStockMutationDepth--;

        if ($outermostScope) {
            try {
                $this->flushDeferredStockMutations();
            } catch (\Throwable $exception) {
                $this->resetDeferredStockMutations();

                throw $exception;
            }
        }

        return $result;
    }

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
                notes: 'Auto-generated to align existing aggregate stock with batch tracking.',
            );

            return;
        }

        $this->queueProductSync($product, $batchQuantity);
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
                ['purchase_id' => $purchase->id, 'purchase_item_id' => $item->id],
            );
        }

        $batch = Batch::create([
            'product_id' => $product->id,
            'purchase_id' => $purchase->id,
            'purchase_item_id' => $item->id,
            'batch_number' => $batchNumber,
            'expiry_date' => $item->expiry_date,
            'received_at' => $purchase->purchase_date?->copy()->startOfDay() ?? now(),
            'storage_location' => $item->storage_location,
            'storage_location_id' => $item->storage_location_id,
            'unit_cost' => $item->unit_price,
            'selling_price' => $item->selling_price ?? $product->selling_price,
            'quantity' => $item->quantity,
            'available_quantity' => $item->quantity,
            'source' => 'purchase',
            'notes' => $purchase->notes,
        ]);

        $this->queueProductSync($product, $productQuantityAfter);

        $this->logInventoryMovement(
            product: $product,
            batch: $batch,
            movementType: 'purchase_receive',
            quantity: $item->quantity,
            quantityBefore: $productQuantityBefore,
            quantityAfter: $productQuantityAfter,
            purchase: $purchase,
            purchaseItem: $item,
            notes: "Batch {$batch->batch_number} received via purchase #{$purchase->id}. Batch availability: 0 -> {$item->quantity}.",
        );

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
        ?string $expiryDate = null,
        ?string $storageLocation = null,
        ?int $storageLocationId = null,
    ): ?Batch {
        if ($quantity <= 0) {
            return null;
        }

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
            'storage_location' => $storageLocation,
            'storage_location_id' => $storageLocationId,
            'unit_cost' => $unitCost,
            'selling_price' => $sellingPrice,
            'quantity' => $quantity,
            'available_quantity' => $quantity,
            'source' => $source,
            'notes' => $notes,
        ]);

        $this->queueProductSync($product, $productQuantityAfter);

        $this->logInventoryMovement(
            product: $product,
            batch: $batch,
            movementType: $source,
            quantity: $quantity,
            quantityBefore: $productQuantityBefore,
            quantityAfter: $productQuantityAfter,
            notes: trim("Batch {$batch->batch_number} created with availability 0 -> {$quantity}. " . ($notes ?? '')),
        );

        return $batch;
    }

    public function adjustProductQuantity(
        Product $product,
        int $targetQuantity,
        int $unitCost,
        ?int $sellingPrice,
        ?string $notes = null,
    ): void {
        $this->ensureBatchCoverage($product);

        $currentQuantity = $this->sumAvailableQuantity($product);

        if ($targetQuantity === $currentQuantity) {
            $this->queueProductSync($product);
            return;
        }

        if ($targetQuantity > $currentQuantity) {
            $this->createManualInboundBatch(
                product: $product,
                quantity: $targetQuantity - $currentQuantity,
                unitCost: $unitCost,
                sellingPrice: $sellingPrice,
                source: 'adjustment_in',
                notes: $notes ?: 'Stock increased from manual product adjustment.',
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
                    . ($notes ?: 'Stock reduced from manual product adjustment.'),
                ),
            );
        }

        $this->queueProductSync($product, $targetQuantity);
    }

    /**
     * Reserve batches for a sale item.
     *
     * @param array<array{batch_id: int, quantity: int}>|null $manualAllocations
     * @return array<array{
     *   batch: Batch,
     *   quantity: int,
     *   unit_cost: int,
     *   batch_quantity_before: int,
     *   batch_quantity_after: int,
     *   product_quantity_before: int,
     *   product_quantity_after: int
     * }>
     */
    public function reserveBatches(Product $product, int $quantity, ?array $manualAllocations = null): array
    {
        $this->ensureBatchCoverage($product);

        if ($manualAllocations !== null) {
            return $this->reserveManualBatches($product, $quantity, $manualAllocations);
        }

        $recommendations = $this->fefoService->recommendBatches(
            product: $product,
            quantity: $quantity,
            policy: BatchAllocationPolicy::FEFO,
            lockForUpdate: true,
        );

        return $this->applyReservations($product, $recommendations, 'sale_out');
    }

    /**
     * @param array<array{batch_id: int, quantity: int}> $manualAllocations
     * @return array<array{
     *   batch: Batch,
     *   quantity: int,
     *   unit_cost: int,
     *   batch_quantity_before: int,
     *   batch_quantity_after: int,
     *   product_quantity_before: int,
     *   product_quantity_after: int
     * }>
     */
    protected function reserveManualBatches(Product $product, int $quantity, array $manualAllocations): array
    {
        $totalAllocated = array_sum(array_map(
            static fn (array $allocation): int => (int) ($allocation['quantity'] ?? 0),
            $manualAllocations,
        ));

        if ($totalAllocated !== $quantity) {
            throw SaleException::invalidBatchAllocation(
                "Total batch allocation ({$totalAllocated}) must equal item quantity ({$quantity}) for product '{$product->name}'."
            );
        }

        $lockedBatches = Batch::query()
            ->where('product_id', $product->id)
            ->whereIn('id', array_column($manualAllocations, 'batch_id'))
            ->lockForUpdate()
            ->get();

        $validatedBatches = $this->fefoService->validateBatchSelection($product, $manualAllocations, $lockedBatches);

        $recommendations = collect($manualAllocations)->map(function (array $allocation) use ($validatedBatches) {
            /** @var Batch $batch */
            $batch = $validatedBatches->get((int) $allocation['batch_id']);

            return [
                'batch' => $batch,
                'quantity' => (int) $allocation['quantity'],
                'unit_cost' => (int) $batch->unit_cost,
            ];
        });

        return $this->applyReservations($product, $recommendations, 'sale_out_manual');
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
                notes: "Batch {$allocation['batch']->batch_number} consumed by sale #{$sale->invoice_number}. Batch availability: {$allocation['batch_quantity_before']} -> {$allocation['batch_quantity_after']}.",
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

            $before = (int) $batch->available_quantity;
            $after = $before + (int) $allocation->quantity;
            $productQuantityBefore = $productQuantityRunning;
            $productQuantityAfter = $productQuantityRunning + (int) $allocation->quantity;
            $this->assertNonNegativeQuantity($productQuantityAfter, 'product quantity', [
                'product_id' => $saleItem->product_id,
                'movement_type' => 'sale_cancel_restore',
            ]);

            $batch->update(['available_quantity' => $after]);

            $this->logInventoryMovement(
                product: $saleItem->product,
                batch: $batch->fresh(),
                movementType: 'sale_cancel_restore',
                quantity: (int) $allocation->quantity,
                quantityBefore: $productQuantityBefore,
                quantityAfter: $productQuantityAfter,
                sale: $sale,
                saleItem: $saleItem,
                notes: "Stock restored after cancelling sale #{$sale->invoice_number}. Batch {$batch->batch_number} availability: {$before} -> {$after}.",
            );

            $productQuantityRunning = $productQuantityAfter;
        }

        $this->queueProductSync($saleItem->product, $productQuantityRunning);

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

            if (!$this->batchPolicyService->canBeConsumed($batch)) {
                throw SaleException::batchNotAvailableForSale(
                    $batch->batch_number,
                    $saleItem->product->name,
                    $this->batchPolicyService->getStatus($batch)->label(),
                );
            }

            if ((int) $batch->available_quantity < (int) $allocation->quantity) {
                throw SaleException::insufficientStock(
                    $saleItem->product->name,
                    (int) $allocation->quantity,
                    (int) $batch->available_quantity,
                );
            }

            $before = (int) $batch->available_quantity;
            $after = $before - (int) $allocation->quantity;
            $this->assertNonNegativeQuantity($after, 'batch available quantity', [
                'batch_id' => $batch->id,
                'movement_type' => 'sale_restore_out',
            ]);

            $productQuantityBefore = $productQuantityRunning;
            $productQuantityAfter = $productQuantityRunning - (int) $allocation->quantity;
            $this->assertNonNegativeQuantity($productQuantityAfter, 'product quantity', [
                'product_id' => $saleItem->product_id,
                'movement_type' => 'sale_restore_out',
            ]);

            $batch->update(['available_quantity' => $after]);

            $this->logInventoryMovement(
                product: $saleItem->product,
                batch: $batch->fresh(),
                movementType: 'sale_restore_out',
                quantity: -(int) $allocation->quantity,
                quantityBefore: $productQuantityBefore,
                quantityAfter: $productQuantityAfter,
                sale: $sale,
                saleItem: $saleItem,
                notes: "Stock re-reserved while restoring sale #{$sale->invoice_number}. Batch {$batch->batch_number} availability: {$before} -> {$after}.",
            );

            $productQuantityRunning = $productQuantityAfter;
        }

        $this->queueProductSync($saleItem->product, $productQuantityRunning);

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
            notes: "Restored stock for sale #{$sale->invoice_number} without original batch allocation history.",
        );
    }

    public function syncProductQuantity(Product $product, ?int $expectedQuantity = null): int
    {
        if (self::$syncingProducts[$product->id] ?? false) {
            return (int) $product->quantity;
        }

        self::$syncingProducts[$product->id] = true;

        try {
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

        if ($quantity > 0) {
            $updateData['purchase_price'] = (int) round($inventoryCostValue / $quantity);
        }

        $dirtyData = [];

        foreach ($updateData as $field => $value) {
            if ((int) $product->getAttribute($field) !== (int) $value) {
                $dirtyData[$field] = $value;
            }
        }

        if ($dirtyData !== []) {
            $product->forceFill($dirtyData)->saveQuietly();
        }

        return $quantity;
        } finally {
            unset(self::$syncingProducts[$product->id]);
        }
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
        ?string $notes = null,
    ): ?InventoryLog {
        if (!$batch && !$purchase && !$sale) {
            throw new \Exception('Inventory log must have at least one reference (batch, purchase, or sale).');
        }

        $this->assertNonNegativeQuantity($quantityBefore, 'inventory log quantity_before', [
            'product_id' => $product->id,
            'movement_type' => $movementType,
        ]);
        $this->assertNonNegativeQuantity($quantityAfter, 'inventory log quantity_after', [
            'product_id' => $product->id,
            'movement_type' => $movementType,
        ]);

        $payload = [
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
        ];

        if ($this->isDeferringStockMutations()) {
            $this->pendingInventoryLogs[] = $payload;

            return null;
        }

        return InventoryLog::create($payload);
    }

    /**
     * @param Collection<int, array{batch: Batch, quantity: int, unit_cost: int}> $recommendations
     * @return array<array{
     *   batch: Batch,
     *   quantity: int,
     *   unit_cost: int,
     *   batch_quantity_before: int,
     *   batch_quantity_after: int,
     *   product_quantity_before: int,
     *   product_quantity_after: int
     * }>
     */
    protected function applyReservations(Product $product, Collection $recommendations, string $movementType): array
    {
        $allocations = [];
        $productQuantityRunning = $this->sumAvailableQuantity($product);

        foreach ($recommendations as $recommendation) {
            /** @var Batch $batch */
            $batch = $recommendation['batch'];
            $requestedQty = (int) $recommendation['quantity'];

            if ($requestedQty <= 0) {
                continue;
            }

            if (!$this->batchPolicyService->canBeConsumed($batch)) {
                throw SaleException::batchNotAvailableForSale(
                    $batch->batch_number,
                    $product->name,
                    $this->batchPolicyService->getStatus($batch)->label(),
                );
            }

            if ((int) $batch->available_quantity < $requestedQty) {
                throw SaleException::insufficientStock(
                    $product->name,
                    $requestedQty,
                    (int) $batch->available_quantity,
                );
            }

            $before = (int) $batch->available_quantity;
            $after = $before - $requestedQty;
            $this->assertNonNegativeQuantity($after, 'batch available quantity', [
                'batch_id' => $batch->id,
                'movement_type' => $movementType,
            ]);

            $productQuantityBefore = $productQuantityRunning;
            $productQuantityAfter = $productQuantityRunning - $requestedQty;
            $this->assertNonNegativeQuantity($productQuantityAfter, 'product quantity', [
                'product_id' => $product->id,
                'movement_type' => $movementType,
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

        $this->queueProductSync($product, $productQuantityRunning);

        return $allocations;
    }

    protected function isDeferringStockMutations(): bool
    {
        return $this->deferredStockMutationDepth > 0;
    }

    protected function queueProductSync(Product $product, ?int $expectedQuantity = null): int
    {
        if (!$this->isDeferringStockMutations()) {
            return $this->syncProductQuantity($product, $expectedQuantity);
        }

        $pendingSync = $this->pendingProductSyncs[$product->id] ?? [
            'product' => $product,
            'expected_quantity' => null,
        ];

        $pendingSync['product'] = $product;

        if ($expectedQuantity !== null || !isset($this->pendingProductSyncs[$product->id])) {
            $pendingSync['expected_quantity'] = $expectedQuantity;
        }

        $this->pendingProductSyncs[$product->id] = $pendingSync;

        return $expectedQuantity ?? (int) $product->quantity;
    }

    protected function flushDeferredStockMutations(): void
    {
        $pendingProductSyncs = $this->pendingProductSyncs;
        $pendingInventoryLogs = $this->pendingInventoryLogs;

        $this->resetDeferredStockMutations();

        foreach ($pendingProductSyncs as $pendingSync) {
            $productId = $pendingSync['product']->id;
            $product = Product::query()->lockForUpdate()->findOrFail($productId);

            $this->syncProductQuantity($product, $pendingSync['expected_quantity']);
        }

        foreach ($pendingInventoryLogs as $payload) {
            InventoryLog::create($payload);
        }
    }

    protected function resetDeferredStockMutations(): void
    {
        $this->pendingProductSyncs = [];
        $this->pendingInventoryLogs = [];
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
