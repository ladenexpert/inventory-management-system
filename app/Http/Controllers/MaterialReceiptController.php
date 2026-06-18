<?php

namespace App\Http\Controllers;

use App\Enums\PurchaseStatus;
use App\Models\Purchase;

class MaterialReceiptController extends Controller
{
    public function index()
    {
        return view('material-receipts.index');
    }

    public function create()
    {
        return view('material-receipts.create', [
            'purchase' => new Purchase(),
            'statuses' => PurchaseStatus::cases(),
            'context' => 'material_receipt',
        ]);
    }

    public function show(Purchase $purchase)
    {
        $purchase->load(['supplier', 'creator', 'items.product.unit', 'items.batch']);

        return view('purchases.show', [
            'purchase' => $purchase,
            'context' => 'material_receipt',
            'indexRoute' => 'material-receipts.index',
            'editRoute' => 'material-receipts.edit',
        ]);
    }

    public function edit(Purchase $purchase)
    {
        abort_if(!in_array($purchase->status, [PurchaseStatus::DRAFT, PurchaseStatus::ORDERED]), 403, 'Only draft or ordered receipts can be edited.');

        $purchase->load('items.product', 'supplier');

        return view('material-receipts.edit', [
            'purchase' => $purchase,
            'statuses' => PurchaseStatus::cases(),
            'context' => 'material_receipt',
        ]);
    }
}
