<?php

namespace App\Http\Middleware;

use App\Services\ModuleService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureModuleEnabled
{
    public function __construct(
        protected ModuleService $moduleService,
    ) {
    }

    public function handle(Request $request, Closure $next, string $module): Response
    {
        if ($this->moduleService->isEnabled($module)) {
            return $next($request);
        }

        $message = ucfirst(str_replace('_', ' ', $module)) . ' module is currently disabled.';

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
            ], Response::HTTP_FORBIDDEN);
        }

        return redirect()
            ->route('dashboard')
            ->with('error', $message);
    }
}
