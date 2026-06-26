<?php

namespace App\Services;

use App\Models\InventoryAdjustment;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class InventoryAdjustmentService
{
    public function __construct(
        protected TransactionCodeService $transactionCodeService,
    ) {
    }

    public function create(array $attributes, string $prefix = 'ADJ'): InventoryAdjustment
    {
        $maxAttempts = 5;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                return DB::transaction(function () use ($attributes, $prefix) {
                    return InventoryAdjustment::create(array_merge($attributes, [
                        'transaction_code' => $this->transactionCodeService->forInventoryAdjustment($prefix),
                    ]));
                });
            } catch (QueryException $exception) {
                if (!$this->isTransactionCodeCollision($exception) || $attempt === $maxAttempts) {
                    throw $exception;
                }

                usleep(50000);
            }
        }

        throw new \RuntimeException('Failed to generate inventory adjustment transaction code.');
    }

    protected function isTransactionCodeCollision(QueryException $exception): bool
    {
        $message = strtolower($exception->getMessage());

        return str_contains($message, 'inventory_adjustments.transaction_code')
            || str_contains($message, 'inventory_adjustments_transaction_code_unique')
            || str_contains($message, "for key 'inventory_adjustments_transaction_code_unique'");
    }
}
