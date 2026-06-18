<?php

namespace App\Models;

use App\Enums\SaleStatus;
use App\Enums\PaymentMethod;
use App\Enums\SaleTransactionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Sale extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_number',
        'transaction_type',
        'customer_id',
        'created_by',
        'sale_date',
        'usage_date',
        'status',
        'subtotal',
        'global_discount',
        'total_discount',
        'total',
        'cash_received',
        'change',
        'payment_method',
        'purpose',
        'formula',
        'project',
        'requested_by',
        'issued_by',
        'notes',
    ];

    protected $casts = [
        'sale_date' => 'datetime',
        'usage_date' => 'datetime',
        'status' => SaleStatus::class,
        'payment_method' => PaymentMethod::class,
        'transaction_type' => SaleTransactionType::class,
        'subtotal' => 'integer',
        'global_discount' => 'integer',
        'total_discount' => 'integer',
        'total' => 'integer',
        'cash_received' => 'integer',
        'change' => 'integer',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function inventoryLogs(): HasMany
    {
        return $this->hasMany(InventoryLog::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }
}
