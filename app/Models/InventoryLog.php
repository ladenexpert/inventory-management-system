<?php

namespace App\Models;

use App\Support\TransactionContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class InventoryLog extends Model
{
    use HasFactory;

    public const MOVEMENT_TYPE_LABELS = [
        'purchase_receive' => 'Purchase / Material Receipt',
        'opening_balance' => 'Opening Stock',
        'adjustment_in' => 'Manual Stock Adjustment In',
        'adjustment_out' => 'Manual Stock Adjustment Out',
        'stock_take_adjustment_in' => 'Stock Take Adjustment In',
        'stock_take_adjustment_out' => 'Stock Take Adjustment Out',
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
        'inventory_adjustment_id',
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
        'inventory_adjustment_id' => 'integer',
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

    public function inventoryAdjustment(): BelongsTo
    {
        return $this->belongsTo(InventoryAdjustment::class);
    }

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class);
    }

    public static function movementTypeOptions(): array
    {
        return collect(array_keys(self::MOVEMENT_TYPE_LABELS))
            ->mapWithKeys(fn (string $type) => [$type => TransactionContext::labelForMovementType($type)])
            ->all();
    }

    public function getMovementTypeLabelAttribute(): string
    {
        return TransactionContext::labelForMovementType($this->movement_type);
    }
}
