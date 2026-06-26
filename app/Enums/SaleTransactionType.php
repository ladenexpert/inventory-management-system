<?php

namespace App\Enums;

enum SaleTransactionType: string
{
    case SALE = 'sale';
    case MATERIAL_USAGE = 'material_usage';

    public function label(): string
    {
        return match ($this) {
            self::SALE => 'Sale',
            self::MATERIAL_USAGE => 'Material Usage',
        };
    }

    public function referencePrefix(): string
    {
        return match ($this) {
            self::SALE => 'INV',
            self::MATERIAL_USAGE => 'MU',
        };
    }

    public function createsFinanceIncome(): bool
    {
        return $this === self::SALE;
    }
}
