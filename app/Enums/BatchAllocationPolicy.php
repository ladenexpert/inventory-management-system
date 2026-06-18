<?php

namespace App\Enums;

enum BatchAllocationPolicy: string
{
    case FEFO = 'fefo';
    case FIFO = 'fifo';
    case MANUAL = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::FEFO => 'FEFO',
            self::FIFO => 'FIFO',
            self::MANUAL => 'Manual',
        };
    }
}
