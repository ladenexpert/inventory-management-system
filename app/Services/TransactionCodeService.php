<?php

namespace App\Services;

use App\Enums\SaleTransactionType;
use Illuminate\Support\Facades\DB;

class TransactionCodeService
{
    public function forSale(SaleTransactionType $transactionType): string
    {
        return $this->generate('sales', 'transaction_code', $transactionType->referencePrefix());
    }

    public function forPurchase(string $entryContext): string
    {
        return $this->generate(
            'purchases',
            'transaction_code',
            $entryContext === 'material_receipt' ? 'MR' : 'PO',
        );
    }

    public function forInventoryAdjustment(string $prefix): string
    {
        return $this->generate('inventory_adjustments', 'transaction_code', $prefix);
    }

    public function forStockTakeSession(): string
    {
        return $this->generate('stock_take_sessions', 'session_code', 'STK');
    }

    public function generate(string $table, string $column, string $prefix): string
    {
        $root = strtoupper(trim($prefix)) . '.' . now()->format('ymd') . '.';

        $latest = DB::table($table)
            ->where($column, 'like', $root . '%')
            ->orderByDesc('id')
            ->value($column);

        if (!$latest) {
            return $root . '0001';
        }

        $lastNumber = (int) substr((string) $latest, -4);

        return $root . str_pad((string) ($lastNumber + 1), 4, '0', STR_PAD_LEFT);
    }
}
