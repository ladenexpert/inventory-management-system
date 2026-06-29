<?php

namespace App\Support;

use Illuminate\Support\Str;

final class RmpTerminology
{
    public const TRANSACTION_NUMBER = 'Transaction Number';
    public const REFERENCE_NUMBER = 'Reference Number';
    public const SKU = 'SKU';
    public const ITEM_CODE = 'Item Code';
    public const MATERIAL_NAME = 'Material Name';
    public const BATCH_NO = 'Batch No';
    public const EXPIRY_DATE = 'Expiry Date';
    public const STORAGE_LOCATION = 'Storage Location';
    public const STOCK_AVAILABLE = 'Stock Available';
    public const INVENTORY_VALUE = 'Inventory Value';
    public const DAYS_REMAINING = 'Days Remaining';
    public const STATUS = 'Status';
    public const TEAM = 'Team';
    public const REQUESTED_BY = 'Requested By';
    public const USAGE_QTY = 'Usage Qty';
    public const COUNTED_QTY = 'Counted Qty';
    public const CURRENT_QTY = 'Current Qty';
    public const VARIANCE = 'Variance';
    public const ADJUSTMENT_TYPE = 'Adjustment Type';
    public const UNIT = 'Unit';
    public const PHYSICAL_FORM = 'Physical Form';
    public const NOTES = 'Notes';

    public static function normalizeHeader(string $header): string
    {
        return Str::of($header)
            ->lower()
            ->replace([' ', '-'], '_')
            ->replaceMatches('/[^a-z0-9_]/', '')
            ->trim('_')
            ->toString();
    }
}
