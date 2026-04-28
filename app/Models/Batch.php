<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Batch extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'purchase_id',
        'purchase_item_id',
        'batch_number',
        'expiry_date',
        'received_at',
        'unit_cost',
        'selling_price',
        'quantity',
        'available_quantity',
        'source',
        'notes',
    ];

    protected $casts = [
        'product_id' => 'integer',
        'purchase_id' => 'integer',
        'purchase_item_id' => 'integer',
        'expiry_date' => 'date',
        'received_at' => 'datetime',
        'unit_cost' => 'integer',
        'selling_price' => 'integer',
        'quantity' => 'integer',
        'available_quantity' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function purchaseItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseItem::class);
    }

    public function inventoryLogs(): HasMany
    {
        return $this->hasMany(InventoryLog::class);
    }

    public function saleItemBatches(): HasMany
    {
        return $this->hasMany(SaleItemBatch::class);
    }
}
