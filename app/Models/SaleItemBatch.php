<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SaleItemBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_item_id',
        'batch_id',
        'quantity',
        'unit_cost',
    ];

    protected $casts = [
        'sale_item_id' => 'integer',
        'batch_id' => 'integer',
        'quantity' => 'integer',
        'unit_cost' => 'integer',
    ];

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }
}
