<?php

namespace App\Services;

use App\Enums\BatchAllocationPolicy;
use App\Exceptions\SaleException;
use App\Models\Batch;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class FefoService
{
    public function __construct(
        protected BatchPolicyService $batchPolicyService
    ) {
    }

    public function recommendBatch(Product $product, BatchAllocationPolicy|string|null $policy = null, bool $lockForUpdate = false): ?Batch
    {
        return $this->eligibleBatchesQuery($product, $policy, $lockForUpdate)->first();
    }

    /**
     * @return Collection<int, array{batch: Batch, quantity: int, unit_cost: int}>
     */
    public function recommendBatches(
        Product $product,
        int $quantity,
        BatchAllocationPolicy|string|null $policy = null,
        bool $lockForUpdate = false
    ): Collection {
        $batches = $this->eligibleBatchesQuery($product, $policy, $lockForUpdate)->get();
        $available = (int) $batches->sum('available_quantity');

        if ($available < $quantity) {
            throw SaleException::insufficientStock($product->name, $quantity, $available);
        }

        $remaining = $quantity;
        $recommendations = collect();

        foreach ($batches as $batch) {
            if ($remaining <= 0) {
                break;
            }

            $deducted = min($remaining, (int) $batch->available_quantity);

            if ($deducted <= 0) {
                continue;
            }

            $recommendations->push([
                'batch' => $batch,
                'quantity' => $deducted,
                'unit_cost' => (int) $batch->unit_cost,
            ]);

            $remaining -= $deducted;
        }

        return $recommendations;
    }

    /**
     * @param array<array{batch_id: int, quantity: int}> $manualAllocations
     * @return Collection<int, Batch>
     */
    public function validateBatchSelection(Product $product, array $manualAllocations, ?Collection $lockedBatches = null): Collection
    {
        if (empty($manualAllocations)) {
            throw SaleException::invalidBatchAllocation("At least one manual batch allocation is required for product '{$product->name}'.");
        }

        $batchIds = array_map(static fn (mixed $id): int => (int) $id, array_column($manualAllocations, 'batch_id'));

        if (count($batchIds) !== count(array_unique($batchIds))) {
            throw SaleException::invalidBatchAllocation("Duplicate batch selections are not allowed for product '{$product->name}'.");
        }

        $batches = ($lockedBatches ?? Batch::query()
            ->where('product_id', $product->id)
            ->whereIn('id', $batchIds)
            ->lockForUpdate()
            ->get())
            ->keyBy('id');

        $missingBatches = array_diff($batchIds, $batches->keys()->all());

        if (!empty($missingBatches)) {
            throw SaleException::invalidBatchAllocation(
                "One or more selected batches do not belong to product '{$product->name}'."
            );
        }

        foreach ($manualAllocations as $allocation) {
            $requestedQty = (int) ($allocation['quantity'] ?? 0);

            if ($requestedQty <= 0) {
                throw SaleException::invalidBatchAllocation(
                    "Manual batch allocation quantities must be greater than zero for product '{$product->name}'."
                );
            }

            /** @var Batch $batch */
            $batch = $batches->get((int) $allocation['batch_id']);

            if (!$this->batchPolicyService->canBeSold($batch)) {
                if ($this->batchPolicyService->isExpired($batch)) {
                    throw SaleException::expiredBatchSelection($batch->batch_number, $product->name);
                }

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
        }

        return $batches;
    }

    protected function eligibleBatchesQuery(
        Product $product,
        BatchAllocationPolicy|string|null $policy = null,
        bool $lockForUpdate = false
    ): Builder {
        $resolvedPolicy = $this->resolvePolicy($policy);
        $today = now()->startOfDay();

        $query = Batch::query()
            ->where('product_id', $product->id)
            ->where('available_quantity', '>', 0)
            ->where(function (Builder $builder) use ($today) {
                $builder
                    ->whereNull('expiry_date')
                    ->orWhereDate('expiry_date', '>=', $today);
            })
            ->where('source', '!=', 'quarantined');

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return match ($resolvedPolicy) {
            BatchAllocationPolicy::FIFO => $query
                ->orderBy('received_at')
                ->orderBy('id'),
            BatchAllocationPolicy::MANUAL => $query->orderBy('id'),
            BatchAllocationPolicy::FEFO => $query
                ->orderByRaw('CASE WHEN expiry_date IS NULL THEN 1 ELSE 0 END')
                ->orderBy('expiry_date')
                ->orderBy('received_at')
                ->orderBy('id'),
        };
    }

    protected function resolvePolicy(BatchAllocationPolicy|string|null $policy = null): BatchAllocationPolicy
    {
        if ($policy instanceof BatchAllocationPolicy) {
            return $policy;
        }

        return BatchAllocationPolicy::tryFrom(strtolower((string) $policy)) ?? BatchAllocationPolicy::FEFO;
    }
}
