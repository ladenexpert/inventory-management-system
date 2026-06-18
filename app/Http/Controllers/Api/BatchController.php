<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BatchPolicyService;
use App\Models\Batch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BatchController extends Controller
{
    public function __construct(
        protected BatchPolicyService $batchPolicyService,
    ) {
    }

    /**
     * Get available batches for a product (for manual selection in sales).
     */
    public function getBatches(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|integer|exists:products,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $productId = $request->input('product_id');

        $batches = Batch::query()
            ->where('product_id', $productId)
            ->where('available_quantity', '>', 0)
            ->orderByRaw('CASE WHEN expiry_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('expiry_date')
            ->orderBy('received_at')
            ->get()
            ->map(function ($batch) {
                $status = $this->batchPolicyService->getStatus($batch);

                return [
                    'id' => $batch->id,
                    'batch_number' => $batch->batch_number,
                    'available_quantity' => $batch->available_quantity,
                    'quantity' => $batch->quantity,
                    'expiry_date' => $batch->expiry_date?->format('Y-m-d'),
                    'expiry_formatted' => $batch->expiry_date?->format('d/m/Y') ?? 'No expiry',
                    'is_expired' => $this->batchPolicyService->isExpired($batch),
                    'is_near_expiry' => $this->batchPolicyService->isNearExpiry($batch),
                    'status' => $status->value,
                    'status_label' => $status->label(),
                    'can_be_consumed' => $this->batchPolicyService->canBeConsumed($batch),
                    'can_be_sold' => $this->batchPolicyService->canBeSold($batch),
                    'unit_cost' => $batch->unit_cost,
                    'inventory_value' => $this->batchPolicyService->inventoryValue($batch),
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $batches,
            'meta' => [
                'near_expiry_days' => $this->batchPolicyService->nearExpiryThresholdDays(),
            ],
        ]);
    }
}
