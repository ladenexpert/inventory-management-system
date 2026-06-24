<?php

namespace App\Models;

use App\Enums\FinanceCategoryType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class FinanceTransaction extends Model
{
    use HasFactory;

    protected $table = 'finance_transactions';

    protected $fillable = [
        'code',
        'transaction_date',
        'finance_category_id',
        'amount',
        'description',
        'external_reference',
        'created_by',
        'reference_id',
        'reference_type',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'amount' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(FinanceCategory::class, 'finance_category_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reference()
    {
        return $this->morphTo();
    }

    public function getSourceLabelAttribute(): string
    {
        return match ($this->reference_type) {
            Sale::class => 'Legacy Sale / POS',
            Purchase::class => 'Legacy Purchase',
            default => 'Manual',
        };
    }

    public function getReferenceNumberAttribute(): string
    {
        return $this->external_reference ?: $this->code;
    }

    public function getRelatedDocumentLabelAttribute(): string
    {
        if (!$this->reference) {
            return '-';
        }

        $label = $this->reference instanceof Sale ? 'Sale' : 'Purchase';
        $number = $this->reference->invoice_number ?: ('#' . $this->reference->id);

        return "{$label} {$number}";
    }

    public function getSignedAmountDisplayAttribute(): string
    {
        $type = $this->category?->type ?? null;

        if (!$type instanceof FinanceCategoryType) {
            return format_money($this->amount);
        }

        $prefix = $type === FinanceCategoryType::Income ? '+' : '-';

        return $prefix . ' ' . format_money($this->amount);
    }
}
