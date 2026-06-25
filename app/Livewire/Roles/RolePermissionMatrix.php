<?php

namespace App\Livewire\Roles;

use App\Services\RolePermissionService;
use Livewire\Component;

class RolePermissionMatrix extends Component
{
    public array $permissions = [];

    public function mount(RolePermissionService $permissionService): void
    {
        $this->permissions = $permissionService->allRoleMatrices();
    }

    public function saveRole(string $role, RolePermissionService $permissionService): void
    {
        $permissionService->syncRolePermissions($role, $this->permissions[$role] ?? []);
        $this->permissions[$role] = $permissionService->permissionsForRole($role);

        $this->dispatch('toast', message: 'Role permissions updated successfully.', type: 'success');
    }

    public function render(RolePermissionService $permissionService)
    {
        return view('livewire.roles.role-permission-matrix', [
            'roles' => $permissionService->roleLabels(),
            'modules' => $permissionService->moduleLabels(),
            'actions' => $permissionService->actionLabels(),
        ]);
    }
}
