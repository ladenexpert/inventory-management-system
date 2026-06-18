<?php

namespace App\Enums;

enum UserRole: string
{
    case ADMIN_RNI = 'admin_rni';
    case FORMULATOR = 'formulator';

    public function label(): string
    {
        return match ($this) {
            self::ADMIN_RNI => 'Admin RNI',
            self::FORMULATOR => 'Formulator',
        };
    }
}
