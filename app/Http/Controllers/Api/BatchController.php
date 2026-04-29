<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BatchController extends Controller
{
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
            ->whereNotNull('expiry_date')
            ->orderBy('expiry_date')
            ->orderBy('received_at')
            ->get()
            ->map(function ($batch) {
                return [
                    'id' => $batch->id,
                    'batch_number' => $batch->batch_number,
                    'available_quantity' => $batch->available_quantity,
                    'expiry_date' => $batch->expiry_date?->format('Y-m-d'),
                    'expiry_formatted' => $batch->expiry_date?->format('d/m/Y'),
                    'is_expired' => $batch->expiry_date && $batch->expiry_date->lt(now()->startOfDay()),
                    'unit_cost' => $batch->unit_cost,
                ];
            });

        // Also get batches without expiry date
        $noExpiryBatches = Batch::query()
            ->where('product_id', $productId)
            ->where('available_quantity', '>', 0)
            ->whereNull('expiry_date')
            ->orderBy('received_at')
            ->get()
            ->map(function ($batch) {
                return [
                    'id' => $batch->id,
                    'batch_number' => $batch->batch_number,
                    'available_quantity' => $batch->available_quantity,
                    'expiry_date' => null,
                    'expiry_formatted' => 'No expiry',
                    'is_expired' => false,
                    'unit_cost' => $batch->unit_cost,
                ];
            });

        $allBatches = $batches->concat($noExpiryBatches);

        return response()->json([
            'success' => true,
            'data' => $allBatches,
        ]);
    }
}