<?php

namespace App\Models;

use App\Support\TransactionContext;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryAdjustment extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_code',
        'adjustment_type',
        'direction',
        'source',
        'reference',
        'notes',
        'adjusted_by',
        'imported_by',
        'adjusted_at',
        'imported_at',
        'meta',
    ];

    protected $casts = [
        'adjusted_by' => 'integer',
        'imported_by' => 'integer',
        'adjusted_at' => 'datetime',
        'imported_at' => 'datetime',
        'meta' => 'array',
    ];

    public function adjustmentUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'adjusted_by');
    }

    public function importedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    public function inventoryLogs(): HasMany
    {
        return $this->hasMany(InventoryLog::class);
    }

    public function getContextLabelAttribute(): string
    {
        return TransactionContext::labelForInventoryAdjustmentType($this->adjustment_type);
    }
}
