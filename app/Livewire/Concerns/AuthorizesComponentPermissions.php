<?php

namespace App\Livewire\Concerns;

use Symfony\Component\HttpFoundation\Response;

trait AuthorizesComponentPermissions
{
    protected function hasPermission(string $module, string $action = 'view'): bool
    {
        return auth()->user()?->hasPermission($module, $action) ?? false;
    }

    protected function authorizePermission(string $module, string $action = 'view'): void
    {
        abort_unless(
            $this->hasPermission($module, $action),
            Response::HTTP_FORBIDDEN,
            'You are not authorized to access this feature.'
        );
    }
}
