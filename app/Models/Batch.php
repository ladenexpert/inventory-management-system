<?php

namespace App\Models;

use App\Services\BatchPolicyService;
use App\Support\TransactionContext;
use Illuminate\Database\Eloquent\Casts\Attribute;
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
        'storage_location',
        'storage_location_id',
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
        'storage_location_id' => 'integer',
        'expiry_date' => 'date',
        'received_at' => 'datetime',
        'unit_cost' => 'integer',
        'selling_price' => 'integer',
        'quantity' => 'integer',
        'available_quantity' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
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

    public function storageLocationRecord(): BelongsTo
    {
        return $this->belongsTo(StorageLocation::class, 'storage_location_id')->withTrashed();
    }

    protected function inventoryValue(): Attribute
    {
        return Attribute::get(fn () => app(BatchPolicyService::class)->inventoryValue($this));
    }

    protected function lifecycleStatus(): Attribute
    {
        return Attribute::get(fn () => app(BatchPolicyService::class)->getStatus($this)->value);
    }

    protected function resolvedStorageLocation(): Attribute
    {
        return Attribute::get(function () {
            $location = $this->storageLocationRecord;

            if ($location) {
                return $location->display_label;
            }

            return $this->storage_location ?: '-';
        });
    }

    protected function sourceLabel(): Attribute
    {
        return Attribute::get(function () {
            $purchase = $this->relationLoaded('purchase')
                ? $this->purchase
                : ($this->purchase_id ? $this->purchase()->first() : null);

            $transactionNumber = $purchase?->transaction_code
                ?? $this->getAttribute('source_transaction_number')
                ?? $this->getAttribute('source_transaction_code');
            $entryContext = $purchase?->entry_context ?? $this->getAttribute('source_entry_context');

            return TransactionContext::labelForBatchSource($this->source, $transactionNumber, $entryContext);
        });
    }
}
