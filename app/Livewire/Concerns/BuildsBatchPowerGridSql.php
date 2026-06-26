<?php

namespace App\Livewire\Concerns;

trait BuildsBatchPowerGridSql
{
    protected function usesSqliteGrammar(): bool
    {
        return app('db')->connection()->getDriverName() === 'sqlite';
    }

    protected function storageLocationExpression(): string
    {
        if ($this->usesSqliteGrammar()) {
            return "COALESCE(NULLIF(TRIM(COALESCE(storage_locations.code, '') || CASE WHEN storage_locations.code IS NOT NULL AND storage_locations.name IS NOT NULL THEN ' - ' ELSE '' END || COALESCE(storage_locations.name, '')), ''), batches.storage_location, '-')";
        }

        return "COALESCE(NULLIF(TRIM(CONCAT_WS(' - ', storage_locations.code, storage_locations.name)), ''), batches.storage_location, '-')";
    }

    protected function physicalFormExpression(): string
    {
        return "COALESCE(NULLIF(physical_forms.name, ''), NULLIF(products.physical_form, ''), '-')";
    }

    protected function supplierNameExpression(): string
    {
        return "COALESCE(NULLIF(purchase_suppliers.name, ''), NULLIF(product_suppliers.name, ''), '-')";
    }

    protected function purchaseDocumentExpression(): string
    {
        return "COALESCE(NULLIF(purchases.transaction_code, ''), NULLIF(purchases.invoice_number, ''), '-')";
    }

    protected function unitExpression(): string
    {
        return "COALESCE(NULLIF(units.symbol, ''), NULLIF(units.name, ''), '-')";
    }

    protected function daysRemainingSortExpression(): string
    {
        if ($this->usesSqliteGrammar()) {
            return "CASE WHEN batches.expiry_date IS NULL THEN 99999 ELSE CAST(julianday(date(batches.expiry_date)) - julianday(date('now')) AS INTEGER) END";
        }

        return "CASE WHEN batches.expiry_date IS NULL THEN 99999 ELSE DATEDIFF(batches.expiry_date, CURDATE()) END";
    }

    protected function daysRemainingLabelExpression(): string
    {
        $daysRemainingSort = $this->daysRemainingSortExpression();

        if ($this->usesSqliteGrammar()) {
            return "CASE
                WHEN batches.expiry_date IS NULL THEN 'No expiry'
                WHEN ({$daysRemainingSort}) >= 0 THEN ({$daysRemainingSort}) || ' days'
                ELSE ABS({$daysRemainingSort}) || ' days overdue'
            END";
        }

        return "CASE
            WHEN batches.expiry_date IS NULL THEN 'No expiry'
            WHEN ({$daysRemainingSort}) >= 0 THEN CONCAT({$daysRemainingSort}, ' days')
            ELSE CONCAT(ABS({$daysRemainingSort}), ' days overdue')
        END";
    }

    protected function expiryDisplayExpression(bool $allowNoExpiry = true): string
    {
        if ($this->usesSqliteGrammar()) {
            return $allowNoExpiry
                ? "CASE WHEN batches.expiry_date IS NULL THEN 'No expiry' ELSE strftime('%d/%m/%Y', batches.expiry_date) END"
                : "strftime('%d/%m/%Y', batches.expiry_date)";
        }

        return $allowNoExpiry
            ? "CASE WHEN batches.expiry_date IS NULL THEN 'No expiry' ELSE DATE_FORMAT(batches.expiry_date, '%d/%m/%Y') END"
            : "DATE_FORMAT(batches.expiry_date, '%d/%m/%Y')";
    }

    protected function batchStatusLabelExpression(int $nearExpiryDays): string
    {
        $nearExpiryDays = max(0, $nearExpiryDays);

        if ($this->usesSqliteGrammar()) {
            return "CASE
                WHEN batches.source = 'quarantined' THEN 'Quarantined'
                WHEN batches.available_quantity <= 0 THEN 'Depleted'
                WHEN batches.expiry_date IS NOT NULL AND date(batches.expiry_date) < date('now') THEN 'Expired'
                WHEN batches.expiry_date IS NOT NULL AND date(batches.expiry_date) <= date('now', '+{$nearExpiryDays} day') THEN 'Near Expiry'
                ELSE 'Active'
            END";
        }

        return "CASE
            WHEN batches.source = 'quarantined' THEN 'Quarantined'
            WHEN batches.available_quantity <= 0 THEN 'Depleted'
            WHEN batches.expiry_date IS NOT NULL AND batches.expiry_date < CURDATE() THEN 'Expired'
            WHEN batches.expiry_date IS NOT NULL AND batches.expiry_date <= DATE_ADD(CURDATE(), INTERVAL {$nearExpiryDays} DAY) THEN 'Near Expiry'
            ELSE 'Active'
        END";
    }

    protected function batchStatusSortExpression(int $nearExpiryDays): string
    {
        $nearExpiryDays = max(0, $nearExpiryDays);

        if ($this->usesSqliteGrammar()) {
            return "CASE
                WHEN batches.source = 'quarantined' THEN 5
                WHEN batches.available_quantity <= 0 THEN 4
                WHEN batches.expiry_date IS NOT NULL AND date(batches.expiry_date) < date('now') THEN 3
                WHEN batches.expiry_date IS NOT NULL AND date(batches.expiry_date) <= date('now', '+{$nearExpiryDays} day') THEN 2
                ELSE 1
            END";
        }

        return "CASE
            WHEN batches.source = 'quarantined' THEN 5
            WHEN batches.available_quantity <= 0 THEN 4
            WHEN batches.expiry_date IS NOT NULL AND batches.expiry_date < CURDATE() THEN 3
            WHEN batches.expiry_date IS NOT NULL AND batches.expiry_date <= DATE_ADD(CURDATE(), INTERVAL {$nearExpiryDays} DAY) THEN 2
            ELSE 1
        END";
    }
}
