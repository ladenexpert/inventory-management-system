<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('products')
            ->where('quantity', '>', 0)
            ->orderBy('id')
            ->get()
            ->each(function ($product) use ($now) {
                $exists = DB::table('batches')
                    ->where('product_id', $product->id)
                    ->exists();

                if ($exists) {
                    return;
                }

                $batchNumber = 'LEGACY-' . $product->id . '-' . $product->sku;

                $batchId = DB::table('batches')->insertGetId([
                    'product_id' => $product->id,
                    'purchase_id' => null,
                    'purchase_item_id' => null,
                    'batch_number' => $batchNumber,
                    'expiry_date' => null,
                    'received_at' => $now,
                    'unit_cost' => $product->purchase_price,
                    'selling_price' => $product->selling_price,
                    'quantity' => $product->quantity,
                    'available_quantity' => $product->quantity,
                    'source' => 'legacy_sync',
                    'notes' => 'Auto-generated from existing aggregate stock.',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('inventory_logs')->insert([
                    'product_id' => $product->id,
                    'batch_id' => $batchId,
                    'purchase_id' => null,
                    'purchase_item_id' => null,
                    'sale_id' => null,
                    'sale_item_id' => null,
                    'movement_type' => 'legacy_sync',
                    'quantity' => $product->quantity,
                    'quantity_before' => 0,
                    'quantity_after' => $product->quantity,
                    'notes' => 'Opening batch generated from existing stock before batch tracking was enabled.',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });
    }

    public function down(): void
    {
        $legacyBatchIds = DB::table('batches')
            ->where('source', 'legacy_sync')
            ->pluck('id');

        if ($legacyBatchIds->isNotEmpty()) {
            DB::table('inventory_logs')->whereIn('batch_id', $legacyBatchIds)->delete();
            DB::table('batches')->whereIn('id', $legacyBatchIds)->delete();
        }
    }
};
