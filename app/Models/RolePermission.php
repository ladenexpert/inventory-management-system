<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RolePermission extends Model
{
    protected $fillable = [
        'role',
        'module',
        'can_view',
        'can_create',
        'can_update',
        'can_delete',
        'can_import',
        'can_export',
        'can_confirm',
        'can_cancel',
        'can_restore',
    ];

    protected function casts(): array
    {
        return [
            'can_view' => 'boolean',
            'can_create' => 'boolean',
            'can_update' => 'boolean',
            'can_delete' => 'boolean',
            'can_import' => 'boolean',
            'can_export' => 'boolean',
            'can_confirm' => 'boolean',
            'can_cancel' => 'boolean',
            'can_restore' => 'boolean',
        ];
    }
}
