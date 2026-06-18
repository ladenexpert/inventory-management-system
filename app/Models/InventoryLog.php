<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class InventoryLog extends Model
{
    use HasFactory;

    public const MOVEMENT_TYPE_LABELS = [
        'purchase_receive' => 'Purchase / Material Receipt',
        'opening_balance' => 'Opening Balance',
        'adjustment_in' => 'Stock Adjustment In',
        'adjustment_out' => 'Stock Adjustment Out',
        'sale_out' => 'Material Usage',
        'sale_cancel_restore' => 'Cancellation / Restore',
        'sale_restore_out' => 'Restore Reservation',
        'legacy_sync' => 'Legacy Sync',
        'quarantined' => 'Quarantined',
    ];

    protected $fillable = [
        'product_id',
        'batch_id',
        'purchase_id',
        'purchase_item_id',
        'sale_id',
        'sale_item_id',
        'movement_type',
        'quantity',
        'quantity_before',
        'quantity_after',
        'notes',
    ];

    protected $casts = [
        'product_id' => 'integer',
        'batch_id' => 'integer',
        'purchase_id' => 'integer',
        'purchase_item_id' => 'integer',
        'sale_id' => 'integer',
        'sale_item_id' => 'integer',
        'quantity' => 'integer',
        'quantity_before' => 'integer',
        'quantity_after' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function purchaseItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseItem::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class);
    }

    public static function movementTypeOptions(): array
    {
        return self::MOVEMENT_TYPE_LABELS;
    }

    public function getMovementTypeLabelAttribute(): string
    {
        return self::MOVEMENT_TYPE_LABELS[$this->movement_type] ?? str($this->movement_type)->headline()->toString();
    }
}
