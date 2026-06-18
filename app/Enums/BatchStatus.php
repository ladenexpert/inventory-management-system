<?php

namespace App\Enums;

enum BatchStatus: string
{
    case ACTIVE = 'active';
    case NEAR_EXPIRY = 'near_expiry';
    case EXPIRED = 'expired';
    case DEPLETED = 'depleted';
    case QUARANTINED = 'quarantined';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Active',
            self::NEAR_EXPIRY => 'Near Expiry',
            self::EXPIRED => 'Expired',
            self::DEPLETED => 'Depleted',
            self::QUARANTINED => 'Quarantined',
        };
    }
}
