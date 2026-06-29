<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockTakeRow extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_take_session_id',
        'row_number',
        'status',
        'error_message',
        'product_id',
        'batch_id',
        'inventory_adjustment_id',
        'sku',
        'item_code',
        'material_name',
        'batch_number',
        'expiry_date',
        'storage_location',
        'system_qty',
        'counted_qty',
        'variance_qty',
        'reference',
        'notes',
        'posted_by',
        'closed_by',
        'posted_at',
        'closed_at',
        'meta',
    ];

    protected $casts = [
        'row_number' => 'integer',
        'product_id' => 'integer',
        'batch_id' => 'integer',
        'inventory_adjustment_id' => 'integer',
        'system_qty' => 'integer',
        'counted_qty' => 'integer',
        'variance_qty' => 'integer',
        'posted_by' => 'integer',
        'closed_by' => 'integer',
        'expiry_date' => 'date',
        'posted_at' => 'datetime',
        'closed_at' => 'datetime',
        'meta' => 'array',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(StockTakeSession::class, 'stock_take_session_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function inventoryAdjustment(): BelongsTo
    {
        return $this->belongsTo(InventoryAdjustment::class);
    }

    public function postedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function closedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
