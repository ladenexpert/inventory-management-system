<?php

namespace App\Http\Middleware;

use App\Services\RolePermissionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserPermission
{
    public function __construct(
        protected RolePermissionService $permissionService,
    ) {
    }

    public function handle(Request $request, Closure $next, string $module, string $action = 'view'): Response
    {
        if ($this->permissionService->allows($request->user(), $module, $action)) {
            return $next($request);
        }

        $message = 'You are not authorized to access this feature.';

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
            ], Response::HTTP_FORBIDDEN);
        }

        abort(Response::HTTP_FORBIDDEN, $message);
    }
}
