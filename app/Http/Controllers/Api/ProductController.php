<?php

namespace App\Http\Controllers\Api;

use App\Models\Product;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class ProductController extends Controller
{
    public function search(Request $request)
    {
        $query = $request->input('q') ?? $request->input('search');

        $products = Product::query()
            ->with(['unit'])
            ->withCount([
                'batches as active_batch_count' => fn($q) => $q->where('available_quantity', '>', 0),
            ])
            ->withMin([
                'batches as nearest_expiry_date' => fn($q) => $q
                    ->where('available_quantity', '>', 0)
                    ->whereNotNull('expiry_date'),
            ], 'expiry_date')
            ->where('is_active', true)
            ->where('quantity', '>', 0)
            ->when($query, function ($q) use ($query) {
                $q->where(function ($inner) use ($query) {
                    $inner->where('name', 'like', "%{$query}%")
                        ->orWhere('sku', 'like', "%{$query}%")
                        ->orWhere('item_code_ierp', 'like', "%{$query}%");
                });
            })
            ->limit(50)
            ->get()
            ->map(function ($product) {
                return [
                    'value' => $product->id,
                    'id' => $product->id,
                    'text' => $product->name,
                    'name' => $product->name,
                    'price' => $product->purchase_price,
                    'selling_price' => $product->selling_price,
                    'sku' => $product->sku,
                    'item_code_ierp' => $product->item_code_ierp,
                    'quantity' => $product->quantity,
                    'active_batch_count' => $product->active_batch_count,
                    'nearest_expiry_date' => $product->nearest_expiry_date,
                    'unit' => $product->unit ? [
                        'symbol' => $product->unit->symbol,
                        'name' => $product->unit->name
                    ] : null,
                ];
            });

        return response()->json($products);
    }
}
