<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\RolePermission;
use App\Models\User;
use App\Support\RolePermissionMatrix;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RolePermissionService
{
    public function moduleLabels(): array
    {
        return RolePermissionMatrix::moduleLabels();
    }

    public function actionLabels(): array
    {
        return RolePermissionMatrix::actionLabels();
    }

    public function roleLabels(): array
    {
        return collect(UserRole::cases())
            ->mapWithKeys(fn (UserRole $role) => [$role->value => $role->label()])
            ->all();
    }

    public function allows(?User $user, string $module, string $action = 'view'): bool
    {
        if (!$user || !in_array($action, RolePermissionMatrix::ACTIONS, true)) {
            return false;
        }

        $matrix = $this->permissionsForRole($user->role?->value ?? '');

        return (bool) data_get($matrix, "{$module}.{$action}", false);
    }

    public function allowsAny(?User $user, array $checks): bool
    {
        if (!$user) {
            return false;
        }

        foreach ($checks as $check) {
            if (is_string($check)) {
                [$module, $action] = array_pad(explode('.', $check, 2), 2, 'view');
            } else {
                [$module, $action] = array_pad($check, 2, 'view');
            }

            if ($this->allows($user, (string) $module, (string) $action)) {
                return true;
            }
        }

        return false;
    }

    public function permissionsForRole(string $role): array
    {
        if ($role === '') {
            return RolePermissionMatrix::emptyAccess();
        }

        if (!Schema::hasTable('role_permissions')) {
            return RolePermissionMatrix::defaultsForRole($role);
        }

        return Cache::rememberForever("role_permissions.{$role}", function () use ($role) {
            $defaults = RolePermissionMatrix::defaultsForRole($role);
            $rows = RolePermission::query()
                ->where('role', $role)
                ->get();

            if ($rows->isEmpty()) {
                return $defaults;
            }

            foreach ($rows as $row) {
                $defaults[$row->module] = [
                    'view' => (bool) $row->can_view,
                    'create' => (bool) $row->can_create,
                    'update' => (bool) $row->can_update,
                    'delete' => (bool) $row->can_delete,
                    'import' => (bool) $row->can_import,
                    'export' => (bool) $row->can_export,
                    'confirm' => (bool) $row->can_confirm,
                    'cancel' => (bool) $row->can_cancel,
                    'restore' => (bool) $row->can_restore,
                ];
            }

            return $defaults;
        });
    }

    public function allRoleMatrices(): array
    {
        $matrices = [];

        foreach (array_keys($this->roleLabels()) as $role) {
            $matrices[$role] = $this->permissionsForRole($role);
        }

        return $matrices;
    }

    public function syncRolePermissions(string $role, array $permissions): void
    {
        $normalized = RolePermissionMatrix::emptyAccess();

        foreach ($normalized as $module => $actions) {
            foreach (array_keys($actions) as $action) {
                $normalized[$module][$action] = (bool) data_get($permissions, "{$module}.{$action}", false);
            }
        }

        DB::transaction(function () use ($role, $normalized) {
            foreach ($normalized as $module => $actions) {
                RolePermission::updateOrCreate(
                    [
                        'role' => $role,
                        'module' => $module,
                    ],
                    RolePermissionMatrix::rowPayload($role, $module, $actions),
                );
            }
        });

        $this->forgetRoleCache($role);
    }

    public function seedDefaults(): void
    {
        foreach (RolePermissionMatrix::defaultPermissionsByRole() as $role => $permissions) {
            $this->syncRolePermissions($role, $permissions);
        }
    }

    public function forgetRoleCache(string $role): void
    {
        Cache::forget("role_permissions.{$role}");
    }
}
