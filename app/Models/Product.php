<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'products';

    protected $fillable = [
        'category_id',
        'unit_id',
        'sku',
        'item_code_ierp',
        'name',
        'purchase_price',
        'selling_price',
        'quantity',
        'min_stock',
        'is_active',
        'description',
        'notes',
    ];

    protected $casts = [
        'purchase_price' => 'integer',
        'selling_price' => 'integer',
        'quantity' => 'integer',
        'min_stock' => 'integer',
        'is_active' => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class)->withTrashed();
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class)->withTrashed();
    }

    public function purchaseItems()
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function saleItems()
    {
        return $this->hasMany(SaleItem::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class);
    }

    public function inventoryLogs(): HasMany
    {
        return $this->hasMany(InventoryLog::class);
    }
}
