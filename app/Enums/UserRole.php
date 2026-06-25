<?php

namespace App\Enums;

enum UserRole: string
{
    case ADMIN_RNI = 'admin_rni';
    case FORMULATOR = 'formulator';
    case RM_DESK = 'rm_desk';

    public function label(): string
    {
        return match ($this) {
            self::ADMIN_RNI => 'Admin RNI',
            self::FORMULATOR => 'Formulator',
            self::RM_DESK => 'RM Desk',
        };
    }
}
