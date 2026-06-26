<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Schema;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    public const PHYSICAL_FORM_OPTIONS = [
        'powder' => 'Powder',
        'liquid' => 'Liquid',
        'wax' => 'Wax',
        'paste' => 'Paste',
        'granule' => 'Granule',
        'other' => 'Other',
    ];

    protected $table = 'products';

    protected $fillable = [
        'category_id',
        'unit_id',
        'supplier_id',
        'sku',
        'item_code_ierp',
        'name',
        'physical_form',
        'physical_form_id',
        'purchase_price',
        'selling_price',
        'quantity',
        'min_stock',
        'is_active',
        'description',
        'notes',
    ];

    protected $casts = [
        'supplier_id' => 'integer',
        'physical_form_id' => 'integer',
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

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class)->withTrashed();
    }

    public function physicalForm(): BelongsTo
    {
        return $this->belongsTo(PhysicalForm::class)->withTrashed();
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

    public static function physicalFormOptions(): array
    {
        if (Schema::hasTable('physical_forms')) {
            $databaseOptions = PhysicalForm::query()
                ->orderBy('name')
                ->pluck('name', 'code')
                ->all();

            if ($databaseOptions !== []) {
                return $databaseOptions + self::PHYSICAL_FORM_OPTIONS;
            }
        }

        return self::PHYSICAL_FORM_OPTIONS;
    }

    public function getPhysicalFormLabelAttribute(): string
    {
        $physicalForm = $this->relationLoaded('physicalForm')
            ? $this->physicalForm
            : $this->physicalForm()->withTrashed()->first();

        if ($physicalForm) {
            return $physicalForm->name;
        }

        return self::physicalFormOptions()[$this->physical_form] ?? '-';
    }

    public function getSkuDisplayAttribute(): string
    {
        return $this->sku ?: '-';
    }

    public function getItemCodeIerpDisplayAttribute(): string
    {
        return $this->item_code_ierp ?: '-';
    }
}
