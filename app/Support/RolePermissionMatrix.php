<?php

namespace App\Support;

use App\Enums\UserRole;

class RolePermissionMatrix
{
    public const ACTIONS = [
        'view',
        'create',
        'update',
        'delete',
        'import',
        'export',
        'confirm',
        'cancel',
        'restore',
    ];

    public const MODULES = [
        'dashboard' => 'Dashboard',
        'materials' => 'Materials',
        'batches' => 'Batches',
        'material_receipt' => 'Material Receipt',
        'material_usage' => 'Material Usage',
        'reports' => 'Reports',
        'finance' => 'Finance',
        'master_data' => 'Master Data',
        'opening_stock' => 'Opening Stock',
        'stock_take' => 'Stock Take',
        'legacy_purchase' => 'Legacy Purchase',
        'legacy_sales' => 'Legacy Sales',
        'settings' => 'Settings',
        'user_access' => 'User Access',
        'inventory_value' => 'Inventory Value',
    ];

    public static function moduleLabels(): array
    {
        return self::MODULES;
    }

    public static function actionLabels(): array
    {
        return [
            'view' => 'View',
            'create' => 'Create',
            'update' => 'Update',
            'delete' => 'Delete',
            'import' => 'Import',
            'export' => 'Export',
            'confirm' => 'Confirm',
            'cancel' => 'Cancel',
            'restore' => 'Restore',
        ];
    }

    public static function defaultPermissionsByRole(): array
    {
        return [
            UserRole::ADMIN_RNI->value => self::fullAccess(),
            UserRole::FORMULATOR->value => self::formulatorAccess(),
            UserRole::RM_DESK->value => self::rmDeskAccess(),
        ];
    }

    public static function defaultsForRole(string $role): array
    {
        return self::defaultPermissionsByRole()[$role] ?? self::emptyAccess();
    }

    public static function emptyAccess(): array
    {
        $permissions = [];

        foreach (array_keys(self::MODULES) as $module) {
            $permissions[$module] = self::emptyActions();
        }

        return $permissions;
    }

    public static function rowPayload(string $role, string $module, array $actions): array
    {
        return [
            'role' => $role,
            'module' => $module,
            'can_view' => (bool) ($actions['view'] ?? false),
            'can_create' => (bool) ($actions['create'] ?? false),
            'can_update' => (bool) ($actions['update'] ?? false),
            'can_delete' => (bool) ($actions['delete'] ?? false),
            'can_import' => (bool) ($actions['import'] ?? false),
            'can_export' => (bool) ($actions['export'] ?? false),
            'can_confirm' => (bool) ($actions['confirm'] ?? false),
            'can_cancel' => (bool) ($actions['cancel'] ?? false),
            'can_restore' => (bool) ($actions['restore'] ?? false),
        ];
    }

    protected static function fullAccess(): array
    {
        $permissions = [];

        foreach (array_keys(self::MODULES) as $module) {
            $permissions[$module] = array_fill_keys(self::ACTIONS, true);
        }

        return $permissions;
    }

    protected static function formulatorAccess(): array
    {
        $permissions = self::emptyAccess();

        $permissions['dashboard']['view'] = true;
        $permissions['materials']['view'] = true;
        $permissions['batches']['view'] = true;
        $permissions['material_usage']['view'] = true;
        $permissions['material_usage']['export'] = true;
        $permissions['reports']['view'] = true;
        $permissions['reports']['export'] = true;

        return $permissions;
    }

    protected static function rmDeskAccess(): array
    {
        $permissions = self::emptyAccess();

        $permissions['dashboard']['view'] = true;
        $permissions['materials']['view'] = true;
        $permissions['batches']['view'] = true;
        $permissions['material_usage']['view'] = true;
        $permissions['material_usage']['create'] = true;
        $permissions['material_usage']['export'] = true;
        $permissions['material_usage']['cancel'] = true;
        $permissions['material_usage']['restore'] = true;
        $permissions['reports']['view'] = true;
        $permissions['reports']['export'] = true;

        return $permissions;
    }

    protected static function emptyActions(): array
    {
        return array_fill_keys(self::ACTIONS, false);
    }
}
