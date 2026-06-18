<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PurchaseItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_id',
        'product_id',
        'batch_number',
        'expiry_date',
        'quantity',
        'unit_price',
        'subtotal',
        'selling_price',
    ];

    protected $casts = [
        'purchase_id' => 'integer',
        'product_id' => 'integer',
        'expiry_date' => 'date',
        'quantity' => 'integer',
        'unit_price' => 'integer',
        'subtotal' => 'integer',
        'selling_price' => 'integer',
    ];

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function batch(): HasOne
    {
        return $this->hasOne(Batch::class);
    }
}
