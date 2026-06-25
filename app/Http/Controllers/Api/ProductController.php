<?php

namespace App\Http\Controllers\Api;

use App\Models\Product;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class ProductController extends Controller
{
    public function search(Request $request)
    {
        $user = $request->user();

        abort_unless($user?->hasAnyPermission([
            ['materials', 'view'],
            ['material_usage', 'create'],
            ['legacy_sales', 'create'],
            ['legacy_purchase', 'create'],
            ['material_receipt', 'create'],
            ['reports', 'view'],
        ]), 403);

        $query = $request->input('q') ?? $request->input('search');
        $scope = (string) ($request->input('scope') ?? 'sale');

        $productQuery = Product::query()
            ->with(['unit', 'supplier'])
            ->withCount([
                'batches as active_batch_count' => fn($q) => $q->where('available_quantity', '>', 0),
            ])
            ->withMin([
                'batches as nearest_expiry_date' => fn($q) => $q
                    ->where('available_quantity', '>', 0)
                    ->whereNotNull('expiry_date'),
            ], 'expiry_date')
            ->where('is_active', true)
            ->when($query, function ($q) use ($query) {
                $q->where(function ($inner) use ($query) {
                    $inner->where('name', 'like', "%{$query}%")
                        ->orWhere('sku', 'like', "%{$query}%")
                        ->orWhere('item_code_ierp', 'like', "%{$query}%");
                });
            })
            ->limit(50);

        if ($scope === 'sale') {
            $productQuery->where('quantity', '>', 0);
        }

        $canViewCost = $user?->canViewInventoryValue() || $user?->canAccessFinance();

        $products = $productQuery
            ->get()
            ->map(function ($product) use ($canViewCost) {
                return [
                    'value' => $product->id,
                    'id' => $product->id,
                    'text' => $product->name,
                    'name' => $product->name,
                    'price' => $canViewCost ? $product->purchase_price : null,
                    'selling_price' => $product->selling_price,
                    'sku' => $product->sku,
                    'item_code_ierp' => $product->item_code_ierp,
                    'physical_form' => $product->physical_form,
                    'physical_form_label' => $product->physical_form_label,
                    'supplier_name' => $product->supplier?->name,
                    'quantity' => $product->quantity,
                    'max_stock' => $product->quantity,
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
