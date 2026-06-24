<?php

namespace App\Services;

use Exception;
use App\Models\Product;
use App\Models\Purchase;
use App\DTOs\PurchaseData;
use App\Models\PurchaseItem;
use App\Enums\PurchaseStatus;
use App\Models\StorageLocation;
use Illuminate\Support\Facades\DB;
use App\Exceptions\PurchaseException;

class PurchaseService
{
    public function __construct(
        protected FinanceTransactionService $financeService,
        protected BatchService $batchService
    ) {
    }

    public function createPurchase(PurchaseData $data, int $userId): Purchase
    {
        return DB::transaction(function () use ($data, $userId) {
            try {
                $purchase = Purchase::create([
                    'invoice_number' => $data->invoice_number,
                    'supplier_id' => $data->supplier_id,
                    'purchase_date' => $data->purchase_date,
                    'due_date' => $data->due_date,
                    'status' => $data->status,
                    'notes' => $data->notes,
                    'proof_image' => $data->proof_image,
                    'entry_context' => $data->entry_context,
                    'created_by'     => $userId,
                    'total'          => 0,
                ]);

                $this->syncItems($purchase, $data->items);

                return $purchase;

            } catch (Exception $e) {
                throw PurchaseException::creationFailed($e->getMessage(), ['supplier_id' => $data->supplier_id]);
            }
        });
    }

    public function updatePurchase(Purchase $purchase, PurchaseData $data): Purchase
    {
        return DB::transaction(function () use ($purchase, $data) {
            try {
                if (!in_array($purchase->status, [PurchaseStatus::DRAFT, PurchaseStatus::ORDERED])) {
                    throw PurchaseException::invalidStatus('update', $purchase->status->label(), ['id' => $purchase->id]);
                }

                $purchase->update([
                    'invoice_number' => $data->invoice_number,
                    'supplier_id' => $data->supplier_id,
                    'purchase_date' => $data->purchase_date,
                    'due_date' => $data->due_date,
                    'notes' => $data->notes,
                    'proof_image' => $data->proof_image,
                    'entry_context' => $data->entry_context,
                ]);

                // Full sync of items
                $purchase->items()->delete();
                $this->syncItems($purchase, $data->items);

                return $purchase->refresh();

            } catch (Exception $e) {
                if ($e instanceof PurchaseException)
                    throw $e;
                throw PurchaseException::updateFailed($e->getMessage(), ['id' => $purchase->id]);
            }
        });
    }

    public function deletePurchase(Purchase $purchase): void
    {
        DB::transaction(function () use ($purchase) {
            try {
                if (!in_array($purchase->status, [PurchaseStatus::DRAFT, PurchaseStatus::CANCELLED])) {
                    throw PurchaseException::deletionFailed(
                        "Cannot delete purchase with status [{$purchase->status->label()}]. Only Draft or Cancelled purchases can be deleted.",
                        ['id' => $purchase->id, 'status' => $purchase->status->value]
                    );
                }

                $this->financeService->voidTransaction($purchase);

                $purchase->items()->delete();
                $purchase->delete();

            } catch (Exception $e) {
                if ($e instanceof PurchaseException)
                    throw $e;
                throw PurchaseException::deletionFailed($e->getMessage(), ['id' => $purchase->id]);
            }
        });
    }

    public function markAsOrdered(Purchase $purchase): void
    {
        DB::transaction(function () use ($purchase) {
            $lockedPurchase = Purchase::query()
                ->lockForUpdate()
                ->withCount('items')
                ->findOrFail($purchase->id);

            if ($lockedPurchase->status !== PurchaseStatus::DRAFT) {
                throw PurchaseException::invalidStatus('order', $lockedPurchase->status->label(), ['id' => $lockedPurchase->id]);
            }

            if ($lockedPurchase->items_count === 0) {
                throw PurchaseException::updateFailed("Cannot order a purchase with no items.", ['id' => $lockedPurchase->id]);
            }

            $lockedPurchase->update(['status' => PurchaseStatus::ORDERED]);
        });
    }

    public function markAsReceived(Purchase $purchase, array $receiptData = []): void
    {
        DB::transaction(function () use ($purchase, $receiptData) {
            $lockedPurchase = Purchase::query()
                ->lockForUpdate()
                ->with('items')
                ->findOrFail($purchase->id);

            if (!in_array($lockedPurchase->status, [PurchaseStatus::ORDERED, PurchaseStatus::DRAFT], true)) {
                throw PurchaseException::invalidStatus('receive', $lockedPurchase->status->label(), ['id' => $lockedPurchase->id]);
            }

            if (!empty($receiptData)) {
                $lockedPurchase->update($receiptData);
            }

            if (!$lockedPurchase->isMaterialReceipt() && empty($lockedPurchase->invoice_number)) {
                throw PurchaseException::missingReference('Invoice Number', ['id' => $lockedPurchase->id]);
            }

            if (!$lockedPurchase->isMaterialReceipt() && empty($lockedPurchase->proof_image)) {
                throw PurchaseException::missingReference('Proof Image', ['id' => $lockedPurchase->id]);
            }

            $productPriceSnapshots = [];

            $this->batchService->withinStockMutationScope(function () use ($lockedPurchase, &$productPriceSnapshots) {
                foreach ($lockedPurchase->items as $item) {
                    $product = Product::where('id', $item->product_id)->lockForUpdate()->first();

                    if (!$product) {
                        continue;
                    }

                    $productPriceSnapshots[$product->id] = [
                        'old_buy_price' => $productPriceSnapshots[$product->id]['old_buy_price'] ?? (int) $product->purchase_price,
                        'old_sell_price' => $productPriceSnapshots[$product->id]['old_sell_price'] ?? (int) $product->selling_price,
                        'selling_price' => $item->selling_price ?? ($productPriceSnapshots[$product->id]['selling_price'] ?? null),
                    ];

                    $this->batchService->createBatchFromPurchaseItem($lockedPurchase, $item);
                }
            });

            foreach ($productPriceSnapshots as $productId => $snapshot) {
                $product = Product::whereKey($productId)->lockForUpdate()->first();

                if (!$product) {
                    continue;
                }

                // purchase_price is synchronized from batch valuation (AVCO) by BatchService.
                // selling_price remains a commercial decision and follows explicit purchase line input when provided.
                $updateData = [];
                $priceChangeLog = '';
                $hasPriceChange = false;

                if ((int) $snapshot['old_buy_price'] !== (int) $product->purchase_price) {
                    $hasPriceChange = true;
                    $oldBuy = format_money((int) $snapshot['old_buy_price']);
                    $newBuy = format_money((int) $product->purchase_price);
                    $priceChangeLog .= "\n- Buying Price (AVCO): {$oldBuy} -> {$newBuy}";
                }

                if ($snapshot['selling_price'] !== null) {
                    $updateData['selling_price'] = (int) $snapshot['selling_price'];

                    if ((int) $snapshot['old_sell_price'] !== (int) $snapshot['selling_price']) {
                        $hasPriceChange = true;
                        $oldSell = format_money((int) $snapshot['old_sell_price']);
                        $newSell = format_money((int) $snapshot['selling_price']);
                        $priceChangeLog .= "\n- Selling Price: {$oldSell} -> {$newSell}";
                    }
                }

                if ($hasPriceChange) {
                    $timestamp = now()->format('Y-m-d H:i');
                    $ref = $lockedPurchase->invoice_number ? "Invoice #{$lockedPurchase->invoice_number}" : "Purchase #{$lockedPurchase->id}";
                    $logHeader = "\n\n[System Log - {$timestamp}] Price update via {$ref}:";
                    $updateData['notes'] = trim(($product->notes ?? '') . $logHeader . $priceChangeLog);
                }

                if (!empty($updateData)) {
                    $product->update($updateData);
                }
            }

            $lockedPurchase->update(['status' => PurchaseStatus::RECEIVED]);
        });
    }

    public function markAsPaid(Purchase $purchase): void
    {
        DB::transaction(function () use ($purchase) {
            $lockedPurchase = Purchase::query()
                ->lockForUpdate()
                ->with('supplier')
                ->findOrFail($purchase->id);

            if ($lockedPurchase->isMaterialReceipt()) {
                throw PurchaseException::updateFailed('Material receipts cannot be marked as paid.', ['id' => $lockedPurchase->id]);
            }

            if (!in_array($lockedPurchase->status, [PurchaseStatus::ORDERED, PurchaseStatus::RECEIVED], true)) {
                throw PurchaseException::invalidStatus('pay', $lockedPurchase->status->label(), ['id' => $lockedPurchase->id]);
            }

            if (empty($lockedPurchase->invoice_number)) {
                throw PurchaseException::missingReference('Invoice Number', ['id' => $lockedPurchase->id]);
            }

            if (empty($lockedPurchase->proof_image)) {
                throw PurchaseException::missingReference('Proof Image', ['id' => $lockedPurchase->id]);
            }

            $lockedPurchase->update(['status' => PurchaseStatus::PAID]);

            $this->financeService->recordExpenseFromPurchase($lockedPurchase);
        });
    }

    public function cancelPurchase(Purchase $purchase): void
    {
        DB::transaction(function () use ($purchase) {
            $lockedPurchase = Purchase::query()
                ->lockForUpdate()
                ->findOrFail($purchase->id);

            if ($lockedPurchase->status === PurchaseStatus::RECEIVED || $lockedPurchase->status === PurchaseStatus::PAID) {
                throw PurchaseException::invalidStatus('cancel', $lockedPurchase->status->label(), ['id' => $lockedPurchase->id]);
            }

            $lockedPurchase->update(['status' => PurchaseStatus::CANCELLED]);

            $this->financeService->voidTransaction($lockedPurchase);
        });
    }

    public function restoreToDraft(Purchase $purchase): void
    {
        DB::transaction(function () use ($purchase) {
            $lockedPurchase = Purchase::query()
                ->lockForUpdate()
                ->findOrFail($purchase->id);

            if ($lockedPurchase->status !== PurchaseStatus::CANCELLED) {
                throw PurchaseException::invalidStatus('restore', $lockedPurchase->status->label(), ['id' => $lockedPurchase->id]);
            }

            $lockedPurchase->update(['status' => PurchaseStatus::DRAFT]);
        });
    }

    private function syncItems(Purchase $purchase, array $items): void
    {
        $total = 0;

        foreach ($items as $itemData) {
            // Validate batch number uniqueness
            if ($itemData->batch_number) {
                $existingBatch = \App\Models\Batch::where('batch_number', $itemData->batch_number)->first();
                if ($existingBatch) {
                    throw new Exception("Batch number '{$itemData->batch_number}' has already been used.");
                }
            }

            $subtotal = $itemData->quantity * $itemData->unit_price;
            $location = $itemData->storage_location_id
                ? StorageLocation::withTrashed()->find($itemData->storage_location_id)
                : null;

            PurchaseItem::create([
                'purchase_id' => $purchase->id,
                'product_id' => $itemData->product_id,
                'batch_number' => $itemData->batch_number,
                'expiry_date' => $itemData->expiry_date,
                'storage_location' => $location?->display_label ?? $itemData->storage_location,
                'storage_location_id' => $location?->id ?? $itemData->storage_location_id,
                'quantity' => $itemData->quantity,
                'unit_price' => $itemData->unit_price,
                'subtotal'    => $subtotal,
                'selling_price' => $itemData->selling_price,
            ]);

            $total += $subtotal;
        }

        $purchase->update(['total' => $total]);
    }
}
